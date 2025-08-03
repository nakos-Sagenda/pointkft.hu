<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates;

use Drupal\Core\Url;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\system\Controller\DbUpdateController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * A batch processor for updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class BatchProcessor {

  /**
   * The session key under which the stage ID is stored.
   *
   * @var string
   */
  public const STAGE_ID_SESSION_KEY = '_automatic_updates_stage_id';

  /**
   * The session key which indicates if the update is done in maintenance mode.
   *
   * @var string
   */
  public const MAINTENANCE_MODE_SESSION_KEY = '_automatic_updates_maintenance_mode';

  /**
   * Gets the updater service.
   *
   * @return \Drupal\automatic_updates\Updater
   *   The updater service.
   */
  protected static function getUpdater(): Updater {
    return \Drupal::service('automatic_updates.updater');
  }

  /**
   * Records messages from a throwable, then re-throws it.
   *
   * @param \Throwable $error
   *   The caught exception.
   * @param array $context
   *   The current context of the batch job.
   *
   * @throws \Throwable
   *   The caught exception, which will always be re-thrown once its messages
   *   have been recorded.
   */
  protected static function handleException(\Throwable $error, array &$context): void {
    $error_messages = [];

    if ($error instanceof StageValidationException) {
      foreach ($error->getResults() as $result) {
        $messages = $result->getMessages();
        if (count($messages) > 1) {
          array_unshift($messages, $result->getSummary());
        }
        $error_messages = array_merge($error_messages, $messages);
      }
    }
    else {
      $error_messages[] = $error->getMessage();
    }

    foreach ($error_messages as $error_message) {
      $context['results']['errors'][] = $error_message;
    }
    throw $error;
  }

  /**
   * Calls the updater's begin() method.
   *
   * @param string[] $project_versions
   *   The project versions to be staged in the update, keyed by package name.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\Updater::begin()
   */
  public static function begin(array $project_versions, array &$context): void {
    try {
      $stage_id = static::getUpdater()->begin($project_versions);
      \Drupal::service('session')->set(static::STAGE_ID_SESSION_KEY, $stage_id);
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the updater's stageVersions() method.
   *
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\Updater::stage()
   */
  public static function stage(array &$context): void {
    try {
      $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
      static::getUpdater()->claim($stage_id)->stage();
    }
    catch (\Throwable $e) {
      static::clean($stage_id, $context);
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the updater's commit() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\Updater::apply()
   */
  public static function commit(string $stage_id, array &$context): void {
    try {
      static::getUpdater()->claim($stage_id)->apply();
      // The batch system does not allow any single request to run for longer
      // than a second, so this will force the next operation to be done in a
      // new request. This helps keep the running code in as consistent a state
      // as possible.
      // @see \Drupal\package_manager\Stage::apply()
      // @see \Drupal\package_manager\Stage::postApply()
      // @todo See if there's a better way to ensure the post-apply tasks run
      //   in a new request in https://www.drupal.org/i/3293150.
      sleep(1);
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the updater's postApply() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\Updater::postApply()
   */
  public static function postApply(string $stage_id, array &$context): void {
    try {
      static::getUpdater()->claim($stage_id)->postApply();
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the updater's clean() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\Updater::clean()
   */
  public static function clean(string $stage_id, array &$context): void {
    try {
      static::getUpdater()->claim($stage_id)->destroy();
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Finishes the stage batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finishStage(bool $success, array $results, array $operations): ?RedirectResponse {
    if ($success) {
      $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
      $url = Url::fromRoute('automatic_updates.confirmation_page', [
        'stage_id' => $stage_id,
      ]);
      return new RedirectResponse($url->setAbsolute()->toString());
    }
    static::handleBatchError($results);
    return NULL;
  }

  /**
   * Finishes the commit batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finishCommit(bool $success, array $results, array $operations): ?RedirectResponse {
    \Drupal::service('session')->remove(static::STAGE_ID_SESSION_KEY);

    if ($success) {
      $url = Url::fromRoute('automatic_updates.finish')
        ->setAbsolute()
        ->toString();
      return new RedirectResponse($url);
    }
    static::handleBatchError($results);
    return NULL;
  }

  /**
   * Handles a batch job that finished with errors.
   *
   * @param array $results
   *   The batch results.
   */
  protected static function handleBatchError(array $results): void {
    if (isset($results['errors'])) {
      foreach ($results['errors'] as $error) {
        \Drupal::messenger()->addError($error);
      }
    }
    else {
      \Drupal::messenger()->addError("Update error");
    }
  }

  /**
   * Reset maintenance mode after update.php.
   *
   * This wraps \Drupal\system\Controller\DbUpdateController::batchFinished()
   * because that function would leave the site in maintenance mode if we
   * redirected the user to update.php already in maintenance mode. We need to
   * take the site out of maintenance mode, if it was not enabled before they
   * submitted our confirmation form.
   *
   * @param bool $success
   *   Whether the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   *
   * @todo Remove the need for this workaround in
   *    https://www.drupal.org/i/3267817.
   *
   * @see \Drupal\update\Form\UpdateReady::submitForm()
   * @see automatic_updates_batch_alter()
   */
  public static function dbUpdateBatchFinished(bool $success, array $results, array $operations): void {
    DbUpdateController::batchFinished($success, $results, $operations);
    // Now that the update is done, we can put the site back online if it was
    // previously not in maintenance mode.
    // \Drupal\system\Controller\DbUpdateController::batchFinished() will not
    // unset maintenance mode if the site was in maintenance mode when the user
    // was redirected to update.php by
    // \Drupal\automatic_updates\Controller\UpdateController::onFinish().
    if (!\Drupal::request()->getSession()->remove(static::MAINTENANCE_MODE_SESSION_KEY)) {
      \Drupal::state()->set('system.maintenance_mode', FALSE);
    }
  }

}

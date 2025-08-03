<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\UnusedConfigFactory;
use Drupal\update\ProjectRelease;
use GuzzleHttp\Psr7\Uri as GuzzleUri;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a service that updates via cron.
 *
 * @internal
 *   This class implements logic specific to Automatic Updates' cron hook
 *   implementation and may be changed or removed at any time without warning.
 *   It should not be called directly, and external code should not interact
 *   with it.
 */
class CronUpdater extends Updater {

  /**
   * All automatic updates are disabled.
   *
   * @var string
   */
  public const DISABLED = 'disable';

  /**
   * Only perform automatic security updates.
   *
   * @var string
   */
  public const SECURITY = 'security';

  /**
   * All automatic updates are enabled.
   *
   * @var string
   */
  public const ALL = 'patch';

  /**
   * The cron release chooser service.
   *
   * @var \Drupal\automatic_updates\ReleaseChooser
   */
  protected $releaseChooser;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The status check mailer service.
   *
   * @var \Drupal\automatic_updates\StatusCheckMailer
   */
  protected $statusCheckMailer;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a CronUpdater object.
   *
   * @param \Drupal\automatic_updates\ReleaseChooser $release_chooser
   *   The cron release chooser service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\automatic_updates\StatusCheckMailer $status_check_mailer
   *   The status check mailer service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param mixed ...$arguments
   *   Additional arguments to pass to the parent constructor.
   */
  public function __construct(ReleaseChooser $release_chooser, LoggerChannelFactoryInterface $logger_factory, MailManagerInterface $mail_manager, StatusCheckMailer $status_check_mailer, StateInterface $state, ...$arguments) {
    $config_factory = $arguments[0];
    $arguments[0] = new UnusedConfigFactory();
    parent::__construct(...$arguments);
    // @todo Remove this in https://www.drupal.org/i/3303167
    $this->configFactory = $config_factory;
    $this->releaseChooser = $release_chooser;
    $this->logger = $logger_factory->get('automatic_updates');
    $this->mailManager = $mail_manager;
    $this->statusCheckMailer = $status_check_mailer;
    $this->state = $state;
  }

  /**
   * Handles updates during cron.
   *
   * @param int|null $timeout
   *   (optional) How long to allow the file copying operation to run before
   *   timing out, in seconds, or NULL to never time out. Defaults to 300
   *   seconds.
   */
  public function handleCron(?int $timeout = 300): void {
    if ($this->getMode() === static::DISABLED) {
      return;
    }

    $next_release = $this->getTargetRelease();
    if ($next_release) {
      $this->performUpdate($next_release->getVersion(), $timeout);
    }
  }

  /**
   * Returns the release of Drupal core to update to, if any.
   *
   * @return \Drupal\update\ProjectRelease|null
   *   The release of Drupal core to which we will update, or NULL if there is
   *   nothing to update to.
   */
  public function getTargetRelease(): ?ProjectRelease {
    return $this->releaseChooser->getLatestInInstalledMinor($this);
  }

  /**
   * {@inheritdoc}
   */
  final public function begin(array $project_versions, ?int $timeout = 300): string {
    // Unattended updates should never be started using this method. They should
    // only be done by ::handleCron(), which has a strong opinion about which
    // release to update to. Throwing an exception here is just to enforce this
    // boundary. To update to a specific version of core, use
    // \Drupal\automatic_updates\Updater::begin() (which is called in
    // ::performUpdate() to start the update to the target version of core
    // chosen by ::handleCron()).
    throw new \BadMethodCallException(__METHOD__ . '() cannot be called directly.');
  }

  /**
   * Performs the update.
   *
   * @param string $target_version
   *   The target version of Drupal core.
   * @param int|null $timeout
   *   How long to allow the operation to run before timing out, in seconds, or
   *   NULL to never time out.
   */
  private function performUpdate(string $target_version, ?int $timeout): void {
    $project_info = new ProjectInfo('drupal');

    if (!$this->isAvailable()) {
      if ($project_info->isInstalledVersionSafe() && !$this->isApplying()) {
        $this->logger->notice('Cron will not perform any updates because there is an existing stage and the current version of the site is secure.');
        return;
      }
      if (!$project_info->isInstalledVersionSafe() && $this->isApplying()) {
        $this->logger->notice(
          'Cron will not perform any updates as an existing staged update is applying. The site is currently on an insecure version of Drupal core but will attempt to update to a secure version next time cron is run. This update may be applied manually at the <a href="%url">update form</a>.',
          ['%url' => Url::fromRoute('update.report_update')->setAbsolute()->toString()],
        );
        return;
      }
    }

    // Delete the existing staging area if not available and the site is
    // currently on an insecure version.
    if (!$project_info->isInstalledVersionSafe() && !$this->isAvailable() && !$this->isApplying()) {
      $destroy_message = $this->t('The existing stage was not in the process of being applied, so it was destroyed to allow updating the site to a secure version during cron.');
      $this->destroy(TRUE, $destroy_message);
      $this->logger->notice($destroy_message->getUntranslatedString());
    }

    $installed_version = $project_info->getInstalledVersion();
    if (empty($installed_version)) {
      $this->logger->error('Unable to determine the current version of Drupal core.');
      return;
    }

    // Do the bulk of the update in its own try-catch structure, so that we can
    // handle any exceptions or validation errors consistently, and destroy the
    // stage regardless of whether the update succeeds.
    try {
      // @see ::begin()
      $stage_id = parent::begin(['drupal' => $target_version], $timeout);
      $this->stage();
      $this->apply();
    }
    catch (\Throwable $e) {
      // Send notifications about the failed update.
      $mail_params = [
        'previous_version' => $installed_version,
        'target_version' => $target_version,
        'error_message' => $e->getMessage(),
      ];
      if ($e instanceof ApplyFailedException || $e->getPrevious() instanceof ApplyFailedException) {
        $mail_params['urgent'] = TRUE;
        $key = 'cron_failed_apply';
      }
      elseif (!$project_info->isInstalledVersionSafe()) {
        $mail_params['urgent'] = TRUE;
        $key = 'cron_failed_insecure';
      }
      else {
        $mail_params['urgent'] = FALSE;
        $key = 'cron_failed';
      }

      foreach ($this->statusCheckMailer->getRecipients() as $email => $langcode) {
        $this->mailManager->mail('automatic_updates', $key, $email, $langcode, $mail_params);
      }
      $this->logger->error($e->getMessage());

      // If an error occurred during the pre-create event, the stage will be
      // marked as available and we shouldn't try to destroy it, since the stage
      // must be claimed in order to be destroyed.
      if (!$this->isAvailable()) {
        $this->destroy();
      }
      return;
    }

    // Perform a subrequest to run ::postApply(), which needs to be done in a
    // separate request.
    // @see parent::apply()
    $url = Url::fromRoute('automatic_updates.cron.post_apply', [
      'stage_id' => $stage_id,
      'installed_version' => $installed_version,
      'target_version' => $target_version,
      'key' => $this->state->get('system.cron_key'),
    ]);
    $this->triggerPostApply($url);
  }

  /**
   * Executes a subrequest to run post-apply tasks.
   *
   * @param \Drupal\Core\Url $url
   *   The URL of the post-apply handler.
   */
  protected function triggerPostApply(Url $url): void {
    $url = $url->setAbsolute()->toString();

    // If we're using a single-threaded web server (e.g., the built-in PHP web
    // server used in build tests), allow the post-apply request to be sent to
    // an alternate port.
    // @todo If using the built-in PHP web server, validate that this port is
    //   set in https://www.drupal.org/i/3293146.
    $port = $this->configFactory->get('automatic_updates.settings')
      ->get('cron_port');
    if ($port) {
      $url = (string) (new GuzzleUri($url))->withPort($port);
    }

    // Use the bare cURL API to make the request, so that we're not relying on
    // any third-party classes or other code which may have changed during the
    // update.
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($status !== 200) {
      $this->logger->error('Post-apply tasks failed with output: %status %response', [
        '%status' => $status,
        '%response' => $response,
      ]);
    }
    curl_close($curl);
  }

  /**
   * Runs post-apply tasks.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param string $installed_version
   *   The version of Drupal core that started the update.
   * @param string $target_version
   *   The version of Drupal core to which we updated.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty 200 response if the post-apply tasks succeeded.
   */
  public function handlePostApply(string $stage_id, string $installed_version, string $target_version): Response {
    $owner = $this->tempStore->getMetadata(static::TEMPSTORE_LOCK_KEY)
      ->getOwnerId();
    // Reload the tempstore with the correct owner ID so we can claim the stage.
    $this->tempStore = $this->tempStoreFactory->get('package_manager_stage', $owner);

    $this->claim($stage_id);

    // Run post-apply tasks in their own try-catch block so that, if anything
    // raises an exception, we'll log it and proceed to destroy the stage as
    // soon as possible (which is also what we do in ::performUpdate()).
    try {
      $this->postApply();

      $this->logger->info(
        'Drupal core has been updated from %previous_version to %target_version',
        [
          '%previous_version' => $installed_version,
          '%target_version' => $target_version,
        ]
      );

      // Send notifications about the successful update.
      $mail_params = [
        'previous_version' => $installed_version,
        'updated_version' => $target_version,
      ];
      foreach ($this->statusCheckMailer->getRecipients() as $recipient => $langcode) {
        $this->mailManager->mail('automatic_updates', 'cron_successful', $recipient, $langcode, $mail_params);
      }
    }
    catch (\Throwable $e) {
      $this->logger->error($e->getMessage());
    }

    // If any pre-destroy event subscribers raise validation errors, ensure they
    // are formatted and logged. But if any pre- or post-destroy event
    // subscribers throw another exception, don't bother catching it, since it
    // will be caught and handled by the main cron service.
    try {
      $this->destroy();
    }
    catch (StageValidationException $e) {
      $this->logger->error($e->getMessage());
    }

    return new Response();
  }

  /**
   * Gets the cron update mode.
   *
   * @return string
   *   The cron update mode. Will be one of the following constants:
   *   - \Drupal\automatic_updates\CronUpdater::DISABLED if updates during cron
   *     are entirely disabled.
   *   - \Drupal\automatic_updates\CronUpdater::SECURITY only security updates
   *     can be done during cron.
   *   - \Drupal\automatic_updates\CronUpdater::ALL if all updates are allowed
   *     during cron.
   */
  final public function getMode(): string {
    $mode = $this->configFactory->get('automatic_updates.settings')->get('cron');
    return $mode ?: CronUpdater::SECURITY;
  }

}

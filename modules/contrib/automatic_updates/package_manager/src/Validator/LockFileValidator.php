<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\package_manager\Event\PostDestroyEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks that the active lock file is unchanged during stage operations.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class LockFileValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The state key under which to store the hash of the active lock file.
   *
   * @var string
   */
  protected const STATE_KEY = 'package_manager.lock_hash';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a LockFileValidator object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(StateInterface $state, PathLocator $path_locator, TranslationInterface $translation) {
    $this->state = $state;
    $this->pathLocator = $path_locator;
    $this->setStringTranslation($translation);
  }

  /**
   * Returns the current hash of the given directory's lock file.
   *
   * @param string $directory
   *   Path of a directory containing a composer.lock file.
   *
   * @return string|false
   *   The hash of the given directory's lock file, or FALSE if the lock file
   *   does not exist.
   */
  protected function getLockFileHash(string $directory) {
    $file = $directory . DIRECTORY_SEPARATOR . 'composer.lock';
    // We want to directly hash the lock file itself, rather than look at its
    // content-hash value, which is actually a hash of the relevant parts of
    // composer.json. We're trying to verify that the actual installed packages
    // have not changed; we don't care about the constraints in composer.json.
    try {
      return hash_file('sha256', $file);
    }
    catch (\Throwable $exception) {
      return FALSE;
    }
  }

  /**
   * Stores the current lock file hash.
   */
  public function storeHash(PreCreateEvent $event): void {
    $hash = $this->getLockFileHash($this->pathLocator->getProjectRoot());
    if ($hash) {
      $this->state->set(static::STATE_KEY, $hash);
    }
    else {
      $event->addError([
        $this->t('Could not hash the active lock file.'),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    // Early return if the stage is not already created.
    if ($event instanceof StatusCheckEvent && $event->getStage()->isAvailable()) {
      return;
    }

    // Ensure we can get a current hash of the lock file.
    $active_hash = $this->getLockFileHash($this->pathLocator->getProjectRoot());
    if (empty($active_hash)) {
      $error = $this->t('Could not hash the active lock file.');
    }

    // Ensure we also have a stored hash of the lock file.
    $stored_hash = $this->state->get(static::STATE_KEY);
    if (empty($stored_hash)) {
      $error = $this->t('Could not retrieve stored hash of the active lock file.');
    }

    // If we have both hashes, ensure they match.
    if ($active_hash && $stored_hash && !hash_equals($stored_hash, $active_hash)) {
      $error = $this->t('Unexpected changes were detected in composer.lock, which indicates that other Composer operations were performed since this Package Manager operation started. This can put the code base into an unreliable state and therefore is not allowed.');
    }

    // Don't allow staged changes to be applied if the staged lock file has no
    // apparent changes.
    if (empty($error) && $event instanceof PreApplyEvent) {
      $stage_hash = $this->getLockFileHash($event->getStage()->getStageDirectory());
      if ($stage_hash && hash_equals($active_hash, $stage_hash)) {
        $error = $this->t('There are no pending Composer operations.');
      }
    }

    // @todo Let the validation result carry all the relevant messages in
    //   https://www.drupal.org/i/3247479.
    if (isset($error)) {
      $event->addError([$error]);
    }
  }

  /**
   * Deletes the stored lock file hash.
   */
  public function deleteHash(): void {
    $this->state->delete(static::STATE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'storeHash',
      PreRequireEvent::class => 'validateStagePreOperation',
      PreApplyEvent::class => 'validateStagePreOperation',
      StatusCheckEvent::class => 'validateStagePreOperation',
      PostDestroyEvent::class => 'deleteHash',
    ];
  }

}

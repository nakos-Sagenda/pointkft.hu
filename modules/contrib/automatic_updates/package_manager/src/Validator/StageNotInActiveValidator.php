<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates staging root is not a subdirectory of active.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class StageNotInActiveValidator implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a new StageNotInActiveValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * Check if staging root is a subdirectory of active.
   */
  public function checkNotInActive(PreOperationStageEvent $event) {
    $project_root = $this->pathLocator->getProjectRoot();
    $staging_root = $this->pathLocator->getStagingRoot();
    if (str_starts_with($staging_root, $project_root)) {
      $message = $this->t("Stage directory is a subdirectory of the active directory.");
      $event->addError([$message]);
      $event->stopPropagation();
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'checkNotInActive',
      StatusCheckEvent::class => 'checkNotInActive',
    ];
  }

}

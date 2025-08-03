<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Excludes 'sites/simpletest' from stage operations.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class TestSiteExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * Constructs a TestSiteExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeTestSites',
    ];
  }

  /**
   * Excludes sites/simpletest from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeTestSites(CollectIgnoredPathsEvent $event): void {
    // Always ignore automated test directories. If they exist, they will be in
    // the web root.
    $this->excludeInWebRoot($event, ['sites/simpletest']);
  }

}

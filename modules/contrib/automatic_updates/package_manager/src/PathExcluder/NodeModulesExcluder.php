<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

/**
 * Excludes node_modules files from stage directories.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class NodeModulesExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * Constructs a NodeModulesExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * Excludes node_modules directories from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeNodeModulesFiles(CollectIgnoredPathsEvent $event): void {
    $finder = Finder::create()
      ->in($this->pathLocator->getProjectRoot())
      ->directories()
      ->name('node_modules')
      ->ignoreVCS(FALSE)
      ->ignoreDotFiles(FALSE);
    $paths = [];
    foreach ($finder as $directory) {
      $paths[] = $directory->getPathname();
    }
    $this->excludeInProjectRoot($event, $paths);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeNodeModulesFiles',
    ];
  }

}

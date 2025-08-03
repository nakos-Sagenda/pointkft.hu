<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\Event\StageEvent;

/**
 * Contains methods for excluding paths from stage operations.
 */
trait PathExclusionsTrait {

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Flags paths to be excluded, relative to the web root.
   *
   * This should only be used for paths that, if they exist at all, are
   * *guaranteed* to exist within the web root.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent|\Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   * @param string[] $paths
   *   The paths to exclude. These should be relative to the web root, and will
   *   be made relative to the project root.
   */
  protected function excludeInWebRoot(StageEvent $event, array $paths): void {
    $web_root = $this->pathLocator->getWebRoot();
    if ($web_root) {
      $web_root .= '/';
    }

    foreach ($paths as $path) {
      // Make the path relative to the project root by prefixing the web root.
      $path = $web_root . $path;

      if ($event instanceof CollectIgnoredPathsEvent) {
        $event->add([$path]);
      }
      else {
        @trigger_error('Passing ' . get_class($event) . ' to ' . __METHOD__ . ' is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.', E_USER_DEPRECATED);
        $event->excludePath($web_root . $path);
      }
    }
  }

  /**
   * Flags paths to be excluded, relative to the project root.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent|\Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   * @param string[] $paths
   *   The paths to exclude. Absolute paths will be made relative to the project
   *   root; relative paths will be assumed to already be relative to the
   *   project root, and excluded as given.
   */
  protected function excludeInProjectRoot(StageEvent $event, array $paths): void {
    $project_root = $this->pathLocator->getProjectRoot();

    foreach ($paths as $path) {
      if (str_starts_with($path, '/')) {
        if (!str_starts_with($path, $project_root)) {
          throw new \LogicException("$path is not inside the project root: $project_root.");
        }
      }

      // Make absolute paths relative to the project root.
      $path = str_replace($project_root, '', $path);
      $path = ltrim($path, '/');

      if ($event instanceof CollectIgnoredPathsEvent) {
        $event->add([$path]);
      }
      else {
        @trigger_error('Passing ' . get_class($event) . ' to ' . __METHOD__ . ' is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.', E_USER_DEPRECATED);
        $event->excludePath($path);
      }
    }
  }

}

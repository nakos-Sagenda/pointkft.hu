<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

/**
 * Common functionality for events which can collect excluded paths.
 */
trait ExcludedPathsTrait {

  /**
   * Paths to exclude from the update.
   *
   * @var string[]
   */
  protected $excludedPaths = [];

  /**
   * Adds a path to exclude from the current operation.
   *
   * If called on an instance of \Drupal\package_manager\Event\PreCreateEvent,
   * excluded paths will not be copied into the stage directory when the stage
   * is created. If called on an instance of
   * \Drupal\package_manager\Event\PreApplyEvent, excluded paths will not be
   * deleted from the active directory when staged changes are applied. So,
   * to ensure that a given path is never staged, but also preserved in the
   * active directory, it should be passed to this method on both PreCreateEvent
   * and PreApplyEvent. See
   * \Drupal\package_manager\EventSubscriber\ExcludedPathsSubscriber for an
   * example.
   *
   * @param string $path
   *   The path to exclude, relative to the project root.
   *
   * @see \Drupal\package_manager\PathExcluder\SiteConfigurationExcluder
   */
  public function excludePath(string $path): void {
    @trigger_error(__METHOD__ . '() is deprecated in automatic_updates:8.x-2.5 and removed in automatic_updates:3.0.0. Use ' . CollectIgnoredPathsEvent::class . ' instead. See https://www.drupal.org/node/3317862.', E_USER_DEPRECATED);
    $this->excludedPaths[] = $path;
  }

  /**
   * Returns the paths to exclude from the current operation.
   *
   * @return string[]
   *   The paths to exclude.
   */
  public function getExcludedPaths(): array {
    return array_unique($this->excludedPaths);
  }

}

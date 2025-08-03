<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\automatic_updates\Event\ReadinessCheckEvent;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains helper methods to run status checks on a stage.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not use or interact with
 *   this trait.
 */
trait StatusCheckTrait {

  /**
   * Runs a status check for a stage and returns the results, if any.
   *
   * @param \Drupal\package_manager\Stage $stage
   *   The stage to run the status check for.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   (optional) The event dispatcher service.
   * @param bool $do_readiness_check
   *   (optional) Whether to also Rerun readiness checks for the stage
   *   (deprecated). Defaults to FALSE.
   *
   * @return \Drupal\package_manager\ValidationResult[]
   *   The results of the status check. If a readiness check was also done,
   *   its results will be included.
   */
  protected function runStatusCheck(Stage $stage, EventDispatcherInterface $event_dispatcher = NULL, bool $do_readiness_check = FALSE): array {
    $event_dispatcher ??= \Drupal::service('event_dispatcher');
    try {
      $ignored_paths = new CollectIgnoredPathsEvent($stage);
      $event_dispatcher->dispatch($ignored_paths);
    }
    catch (\Exception $e) {
      // We can't dispatch the status check event without the ignored paths.
      return [ValidationResult::createErrorFromThrowable($e, t("Unable to collect ignored paths, therefore can't perform status checks."))];
    }

    $event = new StatusCheckEvent($stage, $ignored_paths->getAll());
    $event_dispatcher->dispatch($event);
    $results = $event->getResults();

    if ($do_readiness_check && class_exists(ReadinessCheckEvent::class) && $event_dispatcher->hasListeners(ReadinessCheckEvent::class)) {
      $event = new ReadinessCheckEvent($stage);
      $event_dispatcher->dispatch($event);
      $results = array_merge($results, $event->getResults());
    }
    return $results;
  }

}

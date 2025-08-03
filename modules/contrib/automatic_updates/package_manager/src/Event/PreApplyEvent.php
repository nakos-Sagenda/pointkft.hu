<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

use Drupal\package_manager\Stage;

/**
 * Event fired before staged changes are synced to the active directory.
 */
class PreApplyEvent extends PreOperationStageEvent {

  use ExcludedPathsTrait;

  /**
   * Constructs a PreApplyEvent object.
   *
   * @param \Drupal\package_manager\Stage $stage
   *   The stage which fired this event.
   * @param string[] $ignored_paths
   *   The list of ignored paths.
   */
  public function __construct(Stage $stage, array $ignored_paths = NULL) {
    if ($ignored_paths === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $ignored_paths argument is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.', E_USER_DEPRECATED);
      $ignored_paths = [];
    }
    parent::__construct($stage);
    $this->excludedPaths = $ignored_paths;
  }

}

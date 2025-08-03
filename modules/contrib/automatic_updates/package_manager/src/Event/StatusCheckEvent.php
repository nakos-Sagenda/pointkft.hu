<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Stage;
use Drupal\package_manager\ValidationResult;

/**
 * Event fired to check the status of the system to use Package Manager.
 *
 * The event's stage will be set with the type of stage that will perform the
 * operations. The stage may or may not be currently in use.
 */
class StatusCheckEvent extends PreOperationStageEvent {

  use ExcludedPathsTrait;

  /**
   * Constructs a StatusCheckEvent object.
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

  /**
   * Adds warning information to the event.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   One or more warning messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   A summary of warning messages. Required if there is more than one
   *   message, optional otherwise.
   */
  public function addWarning(array $messages, ?TranslatableMarkup $summary = NULL): void {
    $this->results[] = ValidationResult::createWarning($messages, $summary);
  }

}

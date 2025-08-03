<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

@trigger_error(__NAMESPACE__ . '\PreOperationStageValidatorInterface is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. There is no replacement. See https://www.drupal.org/node/3316086.', E_USER_DEPRECATED);

use Drupal\package_manager\Event\PreOperationStageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an interface for classes that validate a stage before an operation.
 */
interface PreOperationStageValidatorInterface extends EventSubscriberInterface {

  /**
   * Validates a stage before an operation.
   *
   * @param \Drupal\package_manager\Event\PreOperationStageEvent $event
   *   The stage event.
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void;

}

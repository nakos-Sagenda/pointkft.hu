<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Exception;

use Drupal\package_manager\Exception\StageValidationException;

/**
 * Defines a custom exception for a failure during an update.
 *
 * Should not be thrown by external code. This is only used to identify
 * validation errors that occurred during a stage operation performed by
 * Automatic Updates.
 *
 * @see \Drupal\automatic_updates\Updater::dispatch()
 */
class UpdateException extends StageValidationException {
}

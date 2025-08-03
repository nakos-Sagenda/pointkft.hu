<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Exception;

/**
 * Base class for all exceptions related to stage operations.
 *
 * Should not be thrown by external code.
 */
class StageException extends \RuntimeException {
}

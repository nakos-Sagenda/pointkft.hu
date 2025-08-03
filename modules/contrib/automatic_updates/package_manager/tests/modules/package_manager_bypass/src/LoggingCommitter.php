<?php

declare(strict_types = 1);

namespace Drupal\package_manager_bypass;

use Drupal\Core\State\StateInterface;
use PhpTuf\ComposerStager\Domain\Core\Committer\CommitterInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ProcessRunnerInterface;
use PhpTuf\ComposerStager\Domain\Value\Path\PathInterface;
use PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface;

/**
 * A composer-stager Committer decorator that adds logging.
 *
 * @internal
 */
final class LoggingCommitter implements CommitterInterface {

  use LoggingDecoratorTrait;

  /**
   * The decorated service.
   *
   * @var \PhpTuf\ComposerStager\Domain\Core\Committer\CommitterInterface
   */
  private $inner;

  /**
   * Constructs an Committer object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \PhpTuf\ComposerStager\Domain\Core\Committer\CommitterInterface $inner
   *   The decorated committer service.
   */
  public function __construct(StateInterface $state, CommitterInterface $inner) {
    $this->state = $state;
    $this->inner = $inner;
  }

  /**
   * {@inheritdoc}
   */
  public function commit(PathInterface $stagingDir, PathInterface $activeDir, ?PathListInterface $exclusions = NULL, ?ProcessOutputCallbackInterface $callback = NULL, ?int $timeout = ProcessRunnerInterface::DEFAULT_TIMEOUT): void {
    $this->saveInvocationArguments($stagingDir, $activeDir, $exclusions, $timeout);
    if ($exception = $this->state->get(static::class . '-exception')) {
      throw $exception;
    }
    $this->inner->commit($stagingDir, $activeDir, $exclusions, $callback, $timeout);
  }

  /**
   * Sets an exception to be thrown during ::commit().
   *
   * @param \Throwable $exception
   *   The throwable.
   */
  public static function setException(\Throwable $exception): void {
    \Drupal::state()->set(static::class . '-exception', $exception);
  }

}

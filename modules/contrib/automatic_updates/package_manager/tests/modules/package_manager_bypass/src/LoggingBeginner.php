<?php

declare(strict_types = 1);

namespace Drupal\package_manager_bypass;

use Drupal\Core\State\StateInterface;
use PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ProcessRunnerInterface;
use PhpTuf\ComposerStager\Domain\Value\Path\PathInterface;
use PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface;

/**
 * A composer-stager Beginner decorator that adds logging.
 *
 * @internal
 */
final class LoggingBeginner implements BeginnerInterface {

  use LoggingDecoratorTrait;

  /**
   * The decorated service.
   *
   * @var \PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface
   */
  private $inner;

  /**
   * Constructs a Beginner object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface $inner
   *   The decorated beginner service.
   */
  public function __construct(StateInterface $state, BeginnerInterface $inner) {
    $this->state = $state;
    $this->inner = $inner;
  }

  /**
   * {@inheritdoc}
   */
  public function begin(PathInterface $activeDir, PathInterface $stagingDir, ?PathListInterface $exclusions = NULL, ?ProcessOutputCallbackInterface $callback = NULL, ?int $timeout = ProcessRunnerInterface::DEFAULT_TIMEOUT): void {
    $this->saveInvocationArguments($activeDir, $stagingDir, $exclusions, $timeout);
    $this->inner->begin($activeDir, $stagingDir, $exclusions, $callback, $timeout);
  }

}

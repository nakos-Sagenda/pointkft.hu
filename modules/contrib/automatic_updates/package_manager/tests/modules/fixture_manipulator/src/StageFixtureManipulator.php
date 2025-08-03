<?php

declare(strict_types = 1);

namespace Drupal\fixture_manipulator;

use Drupal\Core\State\StateInterface;
use PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ProcessRunnerInterface;
use PhpTuf\ComposerStager\Domain\Value\Path\PathInterface;
use PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface;

/**
 * A fixture manipulator service that commits changes after begin.
 */
final class StageFixtureManipulator extends FixtureManipulator implements BeginnerInterface {

  /**
   * The state key to use.
   */
  private const STATE_KEY = __CLASS__ . 'MANIPULATOR_ARGUMENTS';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The decorated service.
   *
   * @var \PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface
   */
  private BeginnerInterface $inner;

  /**
   * Constructions a StageFixtureManipulator object.
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
    $this->inner->begin($activeDir, $stagingDir, $exclusions, $callback, $timeout);
    if ($this->getQueuedManipulationItems()) {
      $this->doCommitChanges($stagingDir->resolve());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function commitChanges(string $dir): void {
    throw new \BadMethodCallException('::commitChanges() should not be called directly in StageFixtureManipulator().');
  }

  /**
   * {@inheritdoc}
   */
  public function __destruct() {
    // Overrides `__destruct` because the staged fixture manipulator service
    // will be destroyed after every request.
    // @see \Drupal\fixture_manipulator\StageFixtureManipulator::handleTearDown()
  }

  /**
   * Handles test tear down to ensure all changes were committed.
   */
  public static function handleTearDown() {
    if (!empty(\Drupal::state()->get(self::STATE_KEY))) {
      throw new \LogicException('The StageFixtureManipulator has arguments that were not cleared. This likely means that the PostCreateEvent was never fired.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function queueManipulation(string $method, array $arguments): void {
    $stored_arguments = $this->getQueuedManipulationItems();
    $stored_arguments[$method][] = $arguments;
    $this->state->set(self::STATE_KEY, $stored_arguments);
  }

  /**
   * {@inheritdoc}
   */
  protected function clearQueuedManipulationItems(): void {
    $this->state->delete(self::STATE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueuedManipulationItems(): array {
    return $this->state->get(self::STATE_KEY, []);
  }

}

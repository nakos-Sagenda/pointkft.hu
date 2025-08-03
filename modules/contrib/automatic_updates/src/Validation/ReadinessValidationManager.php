<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validation;

/**
 * Defines a manager to run readiness validation.
 */
final class ReadinessValidationManager {

  /**
   * The decorated status checker service.
   *
   * @var \Drupal\automatic_updates\Validation\StatusChecker
   */
  private $statusChecker;

  /**
   * Constructs a ReadinessValidationManager.
   *
   * @param \Drupal\automatic_updates\Validation\StatusChecker $status_checker
   *   The decorated status checker service.
   */
  public function __construct(StatusChecker $status_checker) {
    $this->statusChecker = $status_checker;
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::run().
   */
  public function run(): self {
    $this->statusChecker->run();
    return $this;
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::runIfNoStoredResults().
   */
  public function runIfNoStoredResults(): self {
    $this->statusChecker->runIfNoStoredResults();
    return $this;
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::getResults().
   */
  public function getResults(?int $severity = NULL): ?array {
    return $this->statusChecker->getResults($severity);
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::clearStoredResults().
   */
  public function clearStoredResults(): void {
    $this->statusChecker->clearStoredResults();
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::getLastRunTime().
   */
  public function getLastRunTime(): ?int {
    return $this->statusChecker->getLastRunTime();
  }

  /**
   * Wraps \Drupal\automatic_updates\Validation\StatusChecker::getSubscribedEvents().
   */
  public static function getSubscribedEvents() {
    return StatusChecker::getSubscribedEvents();
  }

}

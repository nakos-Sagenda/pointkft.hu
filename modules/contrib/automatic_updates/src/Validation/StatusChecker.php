<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validation;

use Drupal\automatic_updates\CronUpdater;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\automatic_updates\Updater;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\package_manager\Event\PostApplyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Runs status checks and caches the results.
 */
final class StatusChecker implements EventSubscriberInterface {

  use StatusCheckTrait;

  /**
   * The key/value expirable storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $keyValueExpirable;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The number of hours to store results.
   *
   * @var int
   */
  protected $resultsTimeToLive;

  /**
   * The updater service.
   *
   * @var \Drupal\automatic_updates\Updater
   */
  protected $updater;

  /**
   * The cron updater service.
   *
   * @var \Drupal\automatic_updates\CronUpdater
   */
  protected $cronUpdater;

  /**
   * Constructs a StatusChecker.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The key/value expirable factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher service.
   * @param \Drupal\automatic_updates\Updater $updater
   *   The updater service.
   * @param \Drupal\automatic_updates\CronUpdater $cron_updater
   *   The cron updater service.
   * @param int $results_time_to_live
   *   The number of hours to store results.
   */
  public function __construct(KeyValueExpirableFactoryInterface $key_value_expirable_factory, TimeInterface $time, EventDispatcherInterface $dispatcher, Updater $updater, CronUpdater $cron_updater, int $results_time_to_live) {
    $this->keyValueExpirable = $key_value_expirable_factory->get('automatic_updates');
    $this->time = $time;
    $this->eventDispatcher = $dispatcher;
    $this->updater = $updater;
    $this->cronUpdater = $cron_updater;
    $this->resultsTimeToLive = $results_time_to_live;
  }

  /**
   * Dispatches the status check event and stores the results.
   *
   * @return $this
   */
  public function run(): self {
    // If updates will run during cron, use the cron updater service provided by
    // this module. This will allow validators to run specific validation for
    // conditions that only affect cron updates.
    if ($this->cronUpdater->getMode() === CronUpdater::DISABLED) {
      $stage = $this->updater;
    }
    else {
      $stage = $this->cronUpdater;
    }
    $results = $this->runStatusCheck($stage, $this->eventDispatcher, TRUE);

    $this->keyValueExpirable->setWithExpire(
      'status_check_last_run',
      $results,
      $this->resultsTimeToLive * 60 * 60
    );
    $this->keyValueExpirable->set('status_check_timestamp', $this->time->getRequestTime());
    return $this;
  }

  /**
   * Dispatches the status check event if there no stored valid results.
   *
   * @return $this
   *
   * @see self::getResults()
   */
  public function runIfNoStoredResults(): self {
    if ($this->getResults() === NULL) {
      $this->run();
    }
    return $this;
  }

  /**
   * Gets the validation results from the last run.
   *
   * @param int|null $severity
   *   (optional) The severity for the results to return. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return \Drupal\package_manager\ValidationResult[]|
   *   The validation result objects or NULL if no results are
   *   available or if the stored results are no longer valid.
   */
  public function getResults(?int $severity = NULL): ?array {
    $results = $this->keyValueExpirable->get('status_check_last_run');
    if ($results !== NULL) {
      if ($severity !== NULL) {
        $results = array_filter($results, function ($result) use ($severity) {
          return $result->getSeverity() === $severity;
        });
      }
      return $results;
    }
    return NULL;
  }

  /**
   * Deletes any stored status check results.
   */
  public function clearStoredResults(): void {
    $this->keyValueExpirable->delete('status_check_last_run');
  }

  /**
   * Gets the timestamp of the last run.
   *
   * @return int|null
   *   The timestamp of the last completed run, or NULL if no run has
   *   been completed.
   */
  public function getLastRunTime(): ?int {
    return $this->keyValueExpirable->get('status_check_timestamp');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PostApplyEvent::class => 'clearStoredResults',
    ];
  }

}

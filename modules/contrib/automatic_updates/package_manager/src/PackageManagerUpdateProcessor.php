<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateProcessor;

/**
 * Extends the Update module's update processor allow fetching any project.
 *
 * The Update module's update processor service is intended to only fetch
 * information for projects in the active codebase. Although it would be
 * possible to use the Update module's update processor service to fetch
 * information for projects not in the active code base this would add the
 * project information to Update module's cache which would result in these
 * projects being returned from the Update module's global functions such as
 * update_get_available().
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class PackageManagerUpdateProcessor extends UpdateProcessor {

  /**
   * Constructs an PackageManagerUpdateProcessor object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\update\UpdateFetcherInterface $update_fetcher
   *   The update fetcher service.
   * @param \Drupal\Core\State\StateInterface $state_store
   *   The state service.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key factory service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The expirable key/value factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, QueueFactory $queue_factory, UpdateFetcherInterface $update_fetcher, StateInterface $state_store, PrivateKey $private_key, KeyValueFactoryInterface $key_value_factory, KeyValueExpirableFactoryInterface $key_value_expirable_factory) {
    $this->updateFetcher = $update_fetcher;
    $this->updateSettings = $config_factory->get('update.settings');
    $this->fetchQueue = $queue_factory->get('package_manager.update_fetch_tasks');
    $this->tempStore = $key_value_expirable_factory->get('package_manager.update');
    $this->fetchTaskStore = $key_value_factory->get('package_manager.update_fetch_task');
    $this->availableReleasesTempStore = $key_value_expirable_factory->get('package_manager.update_available_releases');
    $this->stateStore = $state_store;
    $this->privateKey = $private_key;
    $this->fetchTasks = [];
    $this->failed = [];
  }

  /**
   * Gets the project data by name.
   *
   * @param string $name
   *   The project name.
   *
   * @return mixed[]
   *   The project data if any is available, otherwise NULL.
   */
  public function getProjectData(string $name): ?array {
    if ($this->availableReleasesTempStore->has($name)) {
      return $this->availableReleasesTempStore->get($name);
    }
    $project_fetch_data = [
      'name' => $name,
      'project_type' => 'unknown',
      'includes' => [],
    ];
    $this->createFetchTask($project_fetch_data);
    if ($this->processFetchTask($project_fetch_data)) {
      // If the fetch task was successful return the project information.
      return $this->availableReleasesTempStore->get($name);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function processFetchTask($project) {
    // The parent method will set 'update.last_check' which will be used to
    // inform the user when the last time update information was checked. In
    // order to leave this value unaffected we will reset this to it's previous
    // value.
    $last_check = $this->stateStore->get('update.last_check');
    $success = parent::processFetchTask($project);
    $this->stateStore->set('update.last_check', $last_check);
    return $success;
  }

}

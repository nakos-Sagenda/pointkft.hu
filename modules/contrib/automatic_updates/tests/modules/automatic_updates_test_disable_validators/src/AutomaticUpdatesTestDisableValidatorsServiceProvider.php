<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates_test_disable_validators;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;

/**
 * Disables specific validators in the service container.
 */
class AutomaticUpdatesTestDisableValidatorsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    parent::alter($container);

    $validators = Settings::get('automatic_updates_test_disable_validators', []);
    array_walk($validators, [$container, 'removeDefinition']);
  }

}

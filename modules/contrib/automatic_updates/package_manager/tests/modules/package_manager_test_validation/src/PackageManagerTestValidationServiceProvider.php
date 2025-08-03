<?php

declare(strict_types = 1);

namespace Drupal\package_manager_test_validation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies container services for testing.
 */
class PackageManagerTestValidationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);

    $service_id = 'package_manager.validator.staged_database_updates';
    if ($container->hasDefinition($service_id)) {
      $container->getDefinition($service_id)
        ->setClass(StagedDatabaseUpdateValidator::class)
        ->addMethodCall('setState', [
          new Reference('state'),
        ]);
    }
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates;

use Drupal\automatic_updates\Validator\XdebugValidator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies container services for Automatic Updates.
 */
class AutomaticUpdatesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_id = 'package_manager.validator.xdebug';
    if ($container->hasDefinition($service_id)) {
      $container->getDefinition($service_id)
        ->setClass(XdebugValidator::class);
    }
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\package_manager_bypass;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines services to bypass Package Manager's core functionality.
 *
 * @internal
 */
final class PackageManagerBypassServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);

    $state = new Reference('state');
    // By default, \Drupal\package_manager_bypass\NoOpStager is applied, except
    // when a test opts out by setting this setting to FALSE.
    // @see \Drupal\package_manager_bypass\NoOpStager::setLockFileShouldChange()
    if (Settings::get('package_manager_bypass_composer_stager', TRUE)) {
      $container->getDefinition('package_manager.stager')->setClass(NoOpStager::class)->setArguments([$state]);
    }

    $definition = $container->getDefinition('package_manager.path_locator')
      ->setClass(MockPathLocator::class);
    $arguments = $definition->getArguments();
    array_unshift($arguments, $state);
    $definition->setArguments($arguments);
  }

}

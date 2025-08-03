<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;

/**
 * Defines dynamic container services for Package Manager.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class PackageManagerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Use an interface that we know exists to determine the absolute path where
    // Composer Stager is installed.
    $mirror = new \ReflectionClass(BeginnerInterface::class);
    $path = dirname($mirror->getFileName(), 4);

    // Recursively register all classes and interfaces under that directory,
    // relative to the \PhpTuf\ComposerStager namespace.
    $loader = new DirectoryLoader($container, new FileLocator());
    // All the registered services should be auto-wired and private by default.
    $default_definition = new Definition();
    $default_definition->setAutowired(TRUE);
    $default_definition->setPublic(FALSE);
    $loader->registerClasses($default_definition, 'PhpTuf\ComposerStager\\', $path, [
      // Ignore classes which we don't want to register as services.
      $path . '/Domain/Exception',
      $path . '/Infrastructure/Value',
    ]);
  }

}

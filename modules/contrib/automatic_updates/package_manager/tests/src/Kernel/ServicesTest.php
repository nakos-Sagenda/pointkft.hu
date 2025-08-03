<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\package_manager\ExecutableFinder;
use Drupal\package_manager\ProcessFactory;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use PhpTuf\ComposerStager\Infrastructure\Factory\Process\ProcessFactoryInterface;
use PhpTuf\ComposerStager\Infrastructure\Service\Finder\ExecutableFinderInterface;

/**
 * Tests that Package Manager services are wired correctly.
 *
 * @group package_manager
 * @internal
 */
class ServicesTest extends KernelTestBase {

  use AssertPreconditionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['package_manager', 'update'];

  /**
   * Tests that Package Manager's public services can be instantiated.
   */
  public function testPackageManagerServices(): void {
    $services = [
      'package_manager.beginner',
      'package_manager.stager',
      'package_manager.committer',
    ];
    foreach ($services as $service) {
      $this->assertIsObject($this->container->get($service));
    }

    // Ensure that any overridden Composer Stager services were overridden
    // correctly.
    $overrides = [
      ExecutableFinderInterface::class => ExecutableFinder::class,
      ProcessFactoryInterface::class => ProcessFactory::class,
    ];
    foreach ($overrides as $interface => $expected_class) {
      $this->assertInstanceOf($expected_class, $this->container->get($interface));
    }
  }

}

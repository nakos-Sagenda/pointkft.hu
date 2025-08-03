<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel\PathExcluder;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\package_manager\PathExcluder\SiteConfigurationExcluder;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;

/**
 * @covers \Drupal\package_manager\PathExcluder\SiteConfigurationExcluder
 * @group package_manager
 * @internal
 */
class SiteConfigurationExcluderTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // In this test, we want to disable the lock file validator because, even
    // though both the active and stage directories will have a valid lock file,
    // this validator will complain because they don't differ at all.
    $this->disableValidators[] = 'package_manager.validator.lock_file';
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->getDefinition('package_manager.site_configuration_excluder')
      ->setClass(TestSiteConfigurationExcluder::class);
  }

  /**
   * Tests that certain paths are excluded from stage operations.
   */
  public function testExcludedPaths(): void {
    // In this test, we want to perform the actual stage operations so that we
    // can be sure that files are staged as expected.
    $this->setSetting('package_manager_bypass_composer_stager', FALSE);
    // Ensure we have an up-to-date container.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();

    $site_path = 'sites/example.com';

    // Update the event subscribers' dependencies.
    /** @var \Drupal\Tests\package_manager\Kernel\PathExcluder\TestSiteConfigurationExcluder $site_configuration_excluder */
    $site_configuration_excluder = $this->container->get('package_manager.site_configuration_excluder');
    $site_configuration_excluder->sitePath = $site_path;

    $stage = $this->createStage();
    $stage->create();
    $stage_dir = $stage->getStageDirectory();

    $ignore = [
      "$site_path/settings.php",
      "$site_path/settings.local.php",
      "$site_path/services.yml",
      // Default site-specific settings files should be ignored.
      'sites/default/settings.php',
      'sites/default/settings.local.php',
      'sites/default/services.yml',
    ];
    foreach ($ignore as $path) {
      $this->assertFileExists("$active_dir/$path");
      $this->assertFileDoesNotExist("$stage_dir/$path");
    }
    // A non-excluded file in the default site directory should be staged.
    $this->assertFileExists("$stage_dir/sites/default/stage.txt");
    // Regular module files should be staged.
    $this->assertFileExists("$stage_dir/modules/example/example.info.yml");

    // A new file added to the site directory in the stage directory should be
    // copied to the active directory.
    $file = "$stage_dir/sites/default/new.txt";
    touch($file);
    $stage->apply();
    $this->assertFileExists("$active_dir/sites/default/new.txt");

    // The ignored files should still be in the active directory.
    foreach ($ignore as $path) {
      $this->assertFileExists("$active_dir/$path");
    }
  }

}

/**
 * A test version of the site configuration excluder, to expose internals.
 */
class TestSiteConfigurationExcluder extends SiteConfigurationExcluder {

  /**
   * {@inheritdoc}
   */
  public $sitePath;

}

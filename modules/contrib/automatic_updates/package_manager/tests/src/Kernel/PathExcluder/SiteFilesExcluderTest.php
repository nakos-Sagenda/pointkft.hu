<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel\PathExcluder;

use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;

/**
 * @covers \Drupal\package_manager\PathExcluder\SiteFilesExcluder
 * @group package_manager
 * @internal
 */
class SiteFilesExcluderTest extends PackageManagerKernelTestBase {

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
   * Tests that public and private files are excluded from stage operations.
   */
  public function testSiteFilesExcluded(): void {
    // The private stream wrapper is only registered if this setting is set.
    // @see \Drupal\Core\CoreServiceProvider::register()
    $this->setSetting('file_private_path', 'private');
    // In this test, we want to perform the actual stage operations so that we
    // can be sure that files are staged as expected. This will also rebuild
    // the container, enabling the private stream wrapper.
    $this->setSetting('package_manager_bypass_composer_stager', FALSE);
    // Ensure we have an up-to-date container.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();

    // Ensure that we are using directories within the fake site fixture for
    // public and private files.
    $this->setSetting('file_public_path', "sites/example.com/files");

    $stage = $this->createStage();
    $stage->create();
    $stage_dir = $stage->getStageDirectory();

    $ignored = [
      "sites/example.com/files/ignore.txt",
      'private/ignore.txt',
    ];
    foreach ($ignored as $path) {
      $this->assertFileExists("$active_dir/$path");
      $this->assertFileDoesNotExist("$stage_dir/$path");
    }

    $stage->apply();
    // The ignored files should still be in the active directory.
    foreach ($ignored as $path) {
      $this->assertFileExists("$active_dir/$path");
    }
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel\PathExcluder;

use Drupal\Component\FileSystem\FileSystem as DrupalFileSystem;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Drupal\package_manager\PathExcluder\UnknownPathExcluder
 * @group package_manager
 * @internal
 */
class UnknownPathExcluderTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(?string $source_dir = NULL): void {
    // This class needs the test project to be varied for different test
    // methods, so it cannot be called in the setup.
    // @see ::createTestProjectForTemplate()
  }

  /**
   * Creates a test project with or without a nested webroot.
   *
   * @param bool $use_nested_webroot
   *   Whether to use a nested webroot.
   */
  protected function createTestProjectForTemplate(bool $use_nested_webroot): void {
    if (!$use_nested_webroot) {
      // We are not using a nested webroot: the parent test project can be used.
      parent::createTestProject();
    }
    else {
      // Create another directory and copy its contents from fake_site fixture.
      $fake_site_with_nested_webroot = DrupalFileSystem::getOsTemporaryDirectory() . DIRECTORY_SEPARATOR . 'fake_site_with_nested_webroot';
      $fs = new Filesystem();
      if (is_dir($fake_site_with_nested_webroot)) {
        $fs->remove($fake_site_with_nested_webroot);
      }
      $fs->mkdir($fake_site_with_nested_webroot);
      $fs->mirror(__DIR__ . '/../../../fixtures/fake_site', $fake_site_with_nested_webroot);

      // Create a webroot directory in our new directory and copy all folders
      // and files except composer.json, composer.lock and vendor into the
      // webroot.
      $fs->mkdir($fake_site_with_nested_webroot . DIRECTORY_SEPARATOR . 'webroot');
      $paths_in_project_root = glob("$fake_site_with_nested_webroot/*");
      $root_paths = [
        $fake_site_with_nested_webroot . '/vendor',
        $fake_site_with_nested_webroot . '/webroot',
        $fake_site_with_nested_webroot . '/composer.json',
        $fake_site_with_nested_webroot . '/composer.lock',
      ];
      foreach ($paths_in_project_root as $path_in_project_root) {
        if (!in_array($path_in_project_root, $root_paths, TRUE)) {
          $fs->rename($path_in_project_root, $fake_site_with_nested_webroot . '/webroot' . str_replace($fake_site_with_nested_webroot, '', $path_in_project_root));
        }
      }
      parent::createTestProject($fake_site_with_nested_webroot);

      // We need to reset the test paths with our new webroot.
      /** @var \Drupal\package_manager_bypass\MockPathLocator $path_locator */
      $path_locator = $this->container->get('package_manager.path_locator');

      $path_locator->setPaths(
        $path_locator->getProjectRoot(),
        $path_locator->getVendorDirectory(),
        'webroot',
        $path_locator->getStagingRoot()
      );
    }
  }

  /**
   * Data provider for testUnknownPath().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerTestUnknownPath() {
    return [
      'unknown file where web and project root same' => [
        FALSE,
        NULL,
        ['unknown_file.txt'],
      ],
      'unknown file where web and project root different' => [
        TRUE,
        NULL,
        ['unknown_file.txt'],
      ],
      'unknown directory where web and project root same' => [
        FALSE,
        'unknown_dir',
        ['unknown_dir/unknown_dir.README.md', 'unknown_dir/unknown_file.txt'],
      ],
      'unknown directory where web and project root different' => [
        TRUE,
        'unknown_dir',
        ['unknown_dir/unknown_dir.README.md', 'unknown_dir/unknown_file.txt'],
      ],
    ];
  }

  /**
   * Tests that the unknown files and directories are excluded.
   *
   * @param bool $use_nested_webroot
   *   Whether to create test project with a nested webroot.
   * @param string|null $unknown_dir
   *   The path of unknown directory to test or NULL none should be tested.
   * @param string[] $unknown_files
   *   The list of unknown files.
   *
   * @dataProvider providerTestUnknownPath()
   */
  public function testUnknownPath(bool $use_nested_webroot, ?string $unknown_dir, array $unknown_files): void {
    $this->createTestProjectForTemplate($use_nested_webroot);

    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();
    if ($unknown_dir) {
      mkdir("$active_dir/$unknown_dir");
    }
    foreach ($unknown_files as $unknown_file) {
      file_put_contents("$active_dir/$unknown_file", "Unknown File");
    }

    $stage = $this->createStage();
    $stage->create();
    $stage->require(['ext-json:*']);
    $stage_dir = $stage->getStageDirectory();

    foreach ($unknown_files as $path) {
      $this->assertFileExists("$active_dir/$path");
      if ($use_nested_webroot) {
        // It will not exist in stage as it will be excluded because web and
        // project root are different.
        $this->assertFileDoesNotExist("$stage_dir/$path");
      }
      else {
        // If the project root and web root are the same, unknown files will not
        // be excluded, so this path should exist in the stage directory.
        $this->assertFileExists("$stage_dir/$path");
      }
    }

    $stage->apply();
    // The ignored files should still be in the active directory.
    foreach ($unknown_files as $path) {
      $this->assertFileExists("$active_dir/$path");
    }
  }

}

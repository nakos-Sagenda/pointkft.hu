<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use Symfony\Component\Finder\Finder;

/**
 * Tests that core's Composer packages are properly accounted for.
 *
 * In order to identify which Composer packages are part of Drupal core, we need
 * to maintain a single hard-coded list (core_packages.json). This test confirms
 * that the list mentions all of the Composer plugins and metapackages provided
 * by Drupal core.
 *
 * @todo Move this test, and the package list, to a more central place in core.
 *   For example, the list could live in core/assets, and this test could live
 *   in the Drupal\Tests\Composer namespace.
 *
 * @group package_manager
 * @internal
 */
class CorePackageManifestTest extends KernelTestBase {

  use AssertPreconditionsTrait;

  /**
   * Tests that detected core packages match our hard-coded manifest file.
   */
  public function testCorePackagesMatchManifest(): void {
    // Scan for all the composer.json files of said metapackages and plugins,
    // ignoring the project templates. If we are not running in git clone of
    // Drupal core, this will fail since the 'composer' directory won't exist.
    $finder = Finder::create()
      ->in($this->getDrupalRoot() . '/composer')
      ->name('composer.json')
      ->notPath('Template');

    // Always consider drupal/core a valid core package, even though it's not a
    // metapackage or plugin.
    $packages = ['drupal/core'];
    foreach ($finder as $file) {
      $data = Json::decode($file->getContents());
      $packages[] = $data['name'];
    }
    sort($packages);

    // Ensure that the packages we detected matches the hard-coded list we ship.
    $manifest = file_get_contents(__DIR__ . '/../../../core_packages.yml');
    $manifest = Yaml::decode($manifest);
    $this->assertSame($packages, $manifest);
  }

}

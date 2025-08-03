<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Unit;

use Composer\Autoload\ClassLoader;
use Drupal\Tests\UnitTestCase;

/**
 * Tests retrieval of package data from Composer's `installed.php`.
 *
 * ComposerUtility relies on the internal structure of `installed.php` for
 * certain operations. This test is intended as an early warning if the file's
 * internal structure changes in a way that would break our functionality.
 *
 * @group package_manager
 * @internal
 */
class InstalledPackagesDataTest extends UnitTestCase {

  /**
   * Tests that Composer's `installed.php` file looks how we expect.
   */
  public function testInstalledPackagesData(): void {
    $loaders = ClassLoader::getRegisteredLoaders();
    $installed_php = key($loaders) . '/composer/installed.php';
    $this->assertFileIsReadable($installed_php);
    $data = include $installed_php;

    // There should be a `versions` array whose keys are package names.
    $this->assertIsArray($data['versions']);
    $this->assertMatchesRegularExpression('|^[a-z0-9\-_]+/[a-z0-9\-_]+$|', key($data['versions']));

    // The values of `versions` should be arrays of package information that
    // includes a non-empty `install_path` string and a non-empty `type` string.
    $package = reset($data['versions']);
    $this->assertIsArray($package);
    $this->assertNotEmpty($package['install_path']);
    $this->assertIsString($package['install_path']);
    $this->assertNotEmpty($package['type']);
    $this->assertIsString($package['type']);
  }

}

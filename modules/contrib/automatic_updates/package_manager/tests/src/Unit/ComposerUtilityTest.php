<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Unit;

use Composer\Package\PackageInterface;
use Drupal\package_manager\ComposerUtility;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\package_manager\ComposerUtility
 * @group package_manager
 * @internal
 */
class ComposerUtilityTest extends UnitTestCase {

  /**
   * Data provider for ::testCorePackages().
   *
   * @return \string[][][]
   *   The test cases.
   */
  public function providerCorePackages(): array {
    return [
      'core-recommended not installed' => [
        ['drupal/core'],
        ['drupal/core'],
      ],
      'core-recommended installed' => [
        ['drupal/core', 'drupal/core-recommended'],
        ['drupal/core-recommended'],
      ],
    ];
  }

  /**
   * @covers ::getCorePackages
   *
   * @param string[] $installed_package_names
   *   The names of the packages that are installed.
   * @param string[] $expected_core_package_names
   *   The expected core package names that should be returned by
   *   ::getCorePackages().
   *
   * @dataProvider providerCorePackages
   */
  public function testCorePackages(array $installed_package_names, array $expected_core_package_names): void {
    $versions = array_fill(0, count($installed_package_names), '1.0.0');
    $installed_packages = array_combine($installed_package_names, $versions);

    $core_packages = $this->mockUtilityWithPackages($installed_packages)
      ->getCorePackages();
    $this->assertSame($expected_core_package_names, array_keys($core_packages));
  }

  /**
   * @covers ::isValidRequirement
   *
   * @param bool $expected_is_valid
   *   Whether the given requirement string is valid.
   * @param string $requirement
   *   The requirement string to validate.
   *
   * @dataProvider providerIsValidRequirement
   */
  public function testIsValidRequirement(bool $expected_is_valid, string $requirement): void {
    $this->assertSame($expected_is_valid, ComposerUtility::isValidRequirement($requirement));
  }

  /**
   * Data provider for ::testIsValidRequirement().
   *
   * @return \string[][][]
   *   The test cases.
   */
  public function providerIsValidRequirement(): array {
    return [
      // Valid requirements.
      [TRUE, 'vendor/package'],
      [TRUE, 'vendor/snake_case'],
      [TRUE, 'vendor/kebab-case'],
      [TRUE, 'vendor/with.dots'],
      [TRUE, '1vendor2/3package4'],
      [TRUE, 'vendor/package:1'],
      [TRUE, 'vendor/package:1.2'],
      [TRUE, 'vendor/package:1.2.3'],
      [TRUE, 'vendor/package:1.x'],
      [TRUE, 'vendor/package:^1'],
      [TRUE, 'vendor/package:~1'],
      [TRUE, 'vendor/package:>1'],
      [TRUE, 'vendor/package:<1'],
      [TRUE, 'vendor/package:>=1'],
      [TRUE, 'vendor/package:>1 <2'],
      [TRUE, 'vendor/package:1 || 2'],
      [TRUE, 'vendor/package:>=1,<1.1.0'],
      [TRUE, 'vendor/package:1a'],
      [TRUE, 'vendor/package:*'],
      [TRUE, 'vendor/package:dev-master'],
      [TRUE, 'vendor/package:*@dev'],
      [TRUE, 'vendor/package:@dev'],
      [TRUE, 'vendor/package:master@dev'],
      [TRUE, 'vendor/package:master@beta'],
      [TRUE, 'php'],
      [TRUE, 'php:8'],
      [TRUE, 'php:8.0'],
      [TRUE, 'php:^8.1'],
      [TRUE, 'php:~8.1'],
      [TRUE, 'php-64bit'],
      [TRUE, 'composer'],
      [TRUE, 'composer-plugin-api'],
      [TRUE, 'composer-plugin-api:1'],
      [TRUE, 'ext-json'],
      [TRUE, 'ext-json:1'],
      [TRUE, 'ext-pdo_mysql'],
      [TRUE, 'ext-pdo_mysql:1'],
      [TRUE, 'lib-curl'],
      [TRUE, 'lib-curl:1'],
      [TRUE, 'lib-curl-zlib'],
      [TRUE, 'lib-curl-zlib:1'],

      // Invalid requirements.
      [FALSE, ''],
      [FALSE, ' '],
      [FALSE, '/'],
      [FALSE, 'php8'],
      [FALSE, 'package'],
      [FALSE, 'vendor\package'],
      [FALSE, 'vendor//package'],
      [FALSE, 'vendor/package1 vendor/package2'],
      [FALSE, 'vendor/package/extra'],
      [FALSE, 'vendor/package:a'],
      [FALSE, 'vendor/package:'],
      [FALSE, 'vendor/package::'],
      [FALSE, 'vendor/package::1'],
      [FALSE, 'vendor/package:1:2'],
      [FALSE, 'vendor/package:develop@dev@dev'],
      [FALSE, 'vendor/package:develop@'],
      [FALSE, 'vEnDor/pAcKaGe'],
      [FALSE, '_vendor/package'],
      [FALSE, '_vendor/_package'],
      [FALSE, 'vendor_/package'],
      [FALSE, '_vendor/package_'],
      [FALSE, 'vendor/package-'],
      [FALSE, 'php-'],
      [FALSE, 'ext'],
      [FALSE, 'lib'],
    ];
  }

  /**
   * @covers ::getPackagesNotIn
   * @covers ::getPackagesWithDifferentVersionsIn
   */
  public function testPackageComparison(): void {
    $active = $this->mockUtilityWithPackages([
      'drupal/existing' => '1.0.0',
      'drupal/updated' => '1.0.0',
      'drupal/removed' => '1.0.0',
    ]);
    $staged = $this->mockUtilityWithPackages([
      'drupal/existing' => '1.0.0',
      'drupal/updated' => '1.1.0',
      'drupal/added' => '1.0.0',
    ]);

    $added = $staged->getPackagesNotIn($active);
    $this->assertSame(['drupal/added'], array_keys($added));

    $removed = $active->getPackagesNotIn($staged);
    $this->assertSame(['drupal/removed'], array_keys($removed));

    $updated = $active->getPackagesWithDifferentVersionsIn($staged);
    $this->assertSame(['drupal/updated'], array_keys($updated));
  }

  /**
   * Mocks a ComposerUtility object to return a set of installed packages.
   *
   * @param string[]|null[] $installed_packages
   *   The installed packages that the mocked object should return. The keys are
   *   the package names and the values are either a version number or NULL to
   *   not mock the corresponding package's getVersion() method.
   *
   * @return \Drupal\package_manager\ComposerUtility|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked object.
   */
  private function mockUtilityWithPackages(array $installed_packages) {
    $mock = $this->getMockBuilder(ComposerUtility::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getInstalledPackages'])
      ->getMock();

    $packages = [];
    foreach ($installed_packages as $name => $version) {
      $package = $this->createMock(PackageInterface::class);
      if (isset($version)) {
        $package->method('getVersion')->willReturn($version);
      }
      $packages[$name] = $package;
    }
    $mock->method('getInstalledPackages')->willReturn($packages);

    return $mock;
  }

}

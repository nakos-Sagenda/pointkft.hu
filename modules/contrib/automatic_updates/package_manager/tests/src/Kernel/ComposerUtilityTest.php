<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Component\FileSystem\FileSystem as DrupalFileSystem;
use Drupal\fixture_manipulator\FixtureManipulator;
use Drupal\KernelTests\KernelTestBase;
use Drupal\package_manager\ComposerUtility;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @coversDefaultClass \Drupal\package_manager\ComposerUtility
 * @group package_manager
 * @internal
 */
class ComposerUtilityTest extends KernelTestBase {

  use AssertPreconditionsTrait;
  use FixtureUtilityTrait;

  /**
   * The temporary root directory for testing.
   *
   * @var string
   */
  protected string $rootDir;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['package_manager', 'update'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->rootDir = DrupalFileSystem::getOsTemporaryDirectory() . DIRECTORY_SEPARATOR . 'composer_utility_testing_root' . $this->databasePrefix;
    $fs = new Filesystem();
    if (is_dir($this->rootDir)) {
      $fs->remove($this->rootDir);
    }
    $fs->mkdir($this->rootDir);
    $fixture = $this->rootDir . DIRECTORY_SEPARATOR . 'fixture' . DIRECTORY_SEPARATOR;
    static::copyFixtureFilesTo(__DIR__ . '/../../fixtures/fake_site', $fixture);
    $relative_projects_dir = '../../web/projects';
    (new FixtureManipulator())
      ->addPackage(
        [
          'name' => 'drupal/package_project_match',
          'type' => 'drupal-module',
          'install_path' => "$relative_projects_dir/package_project_match",
        ]
      )
      ->addPackage(
        [
          'name' => 'drupal/not_match_package',
          'type' => 'drupal-module',
          'install_path' => "$relative_projects_dir/not_match_project",
        ]
      )
      ->addPackage(
        [
          'name' => 'drupal/not_match_path_project',
          'type' => 'drupal-module',
          'install_path' => "$relative_projects_dir/not_match_project",
        ],
        FALSE,
        FALSE,
      )
      ->addProjectAtPath("web/projects/not_match_path_project", 'not_match_path_project')
      ->addPackage(
        [
          'name' => 'drupal/nested_no_match_package',
          'type' => 'drupal-module',
          'install_path' => "$relative_projects_dir/any_folder_name",
        ],
        FALSE,
        FALSE,
      )
      ->addPackage(
        [
          'name' => 'non_drupal/other_project',
          'type' => 'drupal-module',
          'install_path' => "$relative_projects_dir/other_project",
        ]
      )
      ->addPackage(
        [
          'name' => 'drupal/custom_module',
          'type' => 'drupal-custom-module',
          'install_path' => "$relative_projects_dir/custom_module",
        ]
      )
      // A test info.yml file where the folder names and info.yml file names do
      // not match the project or package. Only the project key in this file
      // need to match.
      ->addProjectAtPath("web/projects/any_folder_name/any_sub_folder", 'nested_no_match_project', 'any_yml_file.info.yml')
      ->commitChanges($fixture);
  }

  /**
   * Tests that ComposerUtility::CreateForDirectory() validates the directory.
   */
  public function testCreateForDirectoryValidation(): void {
    $dir = $this->rootDir;
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Composer could not find the config file: ' . $dir . DIRECTORY_SEPARATOR . 'composer.json');

    ComposerUtility::createForDirectory($dir);
  }

  /**
   * Tests that ComposerUtility disables automatic creation of .htaccess files.
   */
  public function testHtaccessProtectionDisabled(): void {
    $dir = $this->rootDir;
    file_put_contents($dir . '/composer.json', '{}');

    ComposerUtility::createForDirectory($dir);
    $this->assertFileDoesNotExist($dir . '/.htaccess');
  }

  /**
   * @covers ::getProjectForPackage
   *
   * @param string $package
   *   The package name.
   * @param string|null $expected_project
   *   The expected project if any, otherwise NULL.
   *
   * @dataProvider providerGetProjectForPackage
   */
  public function testGetProjectForPackage(string $package, ?string $expected_project): void {
    $dir = $this->rootDir . DIRECTORY_SEPARATOR . 'fixture';
    $this->assertSame($expected_project, ComposerUtility::createForDirectory($dir)->getProjectForPackage($package));
  }

  /**
   * Data provider for ::testGetProjectForPackage().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerGetProjectForPackage(): array {
    return [
      'package and project match' => [
        'drupal/package_project_match',
        'package_project_match',
      ],
      'package and project do not match' => [
        'drupal/not_match_package',
        'not_match_project',
      ],
      'vendor is not drupal' => [
        'non_drupal/other_project',
        NULL,
      ],
      'missing package' => [
        'drupal/missing',
        NULL,
      ],
      'nested_no_match' => [
        'drupal/nested_no_match_package',
        'nested_no_match_project',
      ],
      'unsupported package type' => [
        'drupal/custom_module',
        NULL,
      ],
    ];
  }

  /**
   * @covers ::getPackageForProject
   *
   * @param string $project
   *   The project name.
   * @param string|null $expected_package
   *   The expected package if any, otherwise NULL.
   *
   * @dataProvider providerGetPackageForProject
   */
  public function testGetPackageForProject(string $project, ?string $expected_package): void {
    $dir = $this->rootDir . DIRECTORY_SEPARATOR . 'fixture';
    $this->assertSame($expected_package, ComposerUtility::createForDirectory($dir)->getPackageForProject($project));
  }

  /**
   * Data provider for ::testGetPackageForProject().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerGetPackageForProject(): array {
    return [
      'package and project match' => [
        'package_project_match',
        'drupal/package_project_match',
      ],
      'package and project do not match' => [
        'not_match_project',
        'drupal/not_match_package',
      ],
      'package and project match + wrong installed path' => [
        'not_match_path_project',
        NULL,
      ],
      'vendor is not drupal' => [
        'other_project',
        NULL,
      ],
      'missing package' => [
        'missing',
        NULL,
      ],
      'nested_no_match' => [
        'nested_no_match_project',
        'drupal/nested_no_match_package',
      ],
    ];
  }

}

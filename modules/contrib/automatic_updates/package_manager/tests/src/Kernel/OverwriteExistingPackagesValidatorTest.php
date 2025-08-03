<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;

/**
 * @covers \Drupal\package_manager\Validator\OverwriteExistingPackagesValidator
 * @group package_manager
 * @internal
 */
class OverwriteExistingPackagesValidatorTest extends PackageManagerKernelTestBase {

  use FixtureUtilityTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // In this test, we don't care whether the updated projects are secure and
    // supported.
    $this->disableValidators[] = 'package_manager.validator.supported_releases';
    parent::setUp();
  }

  /**
   * Tests that new installed packages overwrite existing directories.
   *
   * The fixture simulates a scenario where the active directory has three
   * modules installed: module_1, module_2, and module_5. None of them are
   * managed by Composer. These modules will be moved into the stage directory
   * by the 'package_manager_bypass' module.
   */
  public function testNewPackagesOverwriteExisting(): void {
    (new ActiveFixtureManipulator())
      ->addProjectAtPath('modules/module_1')
      ->addProjectAtPath('modules/module_2')
      ->addProjectAtPath('modules/module_5')
      ->commitChanges();
    $stage_manipulator = $this->getStageFixtureManipulator();

    // module_1 and module_2 will raise errors because they would overwrite
    // non-Composer managed paths in the active directory.
    $stage_manipulator
      ->addPackage(
        [
          'name' => 'drupal/module_1',
          'version' => '1.3.0',
          'type' => 'drupal-module',
          'install_path' => '../../modules/module_1',
        ],
        FALSE,
        FALSE
      )
      ->addPackage(
        [
          'name' => 'drupal/module_2',
          'version' => '1.3.0',
          'type' => 'drupal-module',
          'install_path' => '../../modules/module_2',
        ],
        FALSE,
        FALSE
      );

    // module_3 will cause no problems, since it doesn't exist in the active
    // directory at all.
    $stage_manipulator->addPackage([
      'name' => 'drupal/module_3',
      'version' => '1.3.0',
      'type' => 'drupal-module',
      'install_path' => '../../modules/module_3',
    ]);

    // module_4 doesn't exist in the active directory but the 'install_path' as
    // known to Composer in the staged directory collides with module_1 in the
    // active directory which will cause an error.
    $stage_manipulator->addPackage(
      [
        'name' => 'drupal/module_4',
        'version' => '1.3.0',
        'type' => 'drupal-module',
        'install_path' => '../../modules/module_1',
      ],
      FALSE,
      FALSE,
    );

    // module_5_different_path will not cause a problem, even though its package
    // name is drupal/module_5, because its project name and path in the stage
    // directory differ from the active directory.
    $stage_manipulator->addPackage([
      'name' => 'drupal/module_5',
      'version' => '1.3.0',
      'type' => 'drupal-module',
      'install_path' => '../../modules/module_5_different_path',
    ]);

    // Add a package without an install_path set which will not raise an error.
    // The most common example of this in the Drupal ecosystem is a submodule.
    $stage_manipulator->addPackage([
      'name' => 'drupal/sub-module',
      'version' => '1.3.0',
      'type' => 'metapackage',
    ]);

    $expected_results = [
      ValidationResult::createError([
        t('The new package drupal/module_1 will be installed in the directory /vendor/composer/../../modules/module_1, which already exists but is not managed by Composer.'),
      ]),
      ValidationResult::createError([
        t('The new package drupal/module_2 will be installed in the directory /vendor/composer/../../modules/module_2, which already exists but is not managed by Composer.'),
      ]),
      ValidationResult::createError([
        t('The new package drupal/module_4 will be installed in the directory /vendor/composer/../../modules/module_1, which already exists but is not managed by Composer.'),
      ]),
    ];
    $this->assertResults($expected_results, PreApplyEvent::class);
  }

}

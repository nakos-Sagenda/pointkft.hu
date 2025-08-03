<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Build;

use Drupal\package_manager\Stage;

/**
 * Tests updating packages in a stage directory.
 *
 * @group package_manager
 * @internal
 */
class PackageUpdateTest extends TemplateProjectTestBase {

  /**
   * Tests updating packages in a stage directory.
   */
  public function testPackageUpdate(): void {
    $this->createTestProject('RecommendedProject');

    $this->addRepository('alpha', $this->copyFixtureToTempDirectory(__DIR__ . '/../../fixtures/build_test_projects/alpha/1.0.0'));
    $this->addRepository('updated_module', $this->copyFixtureToTempDirectory(__DIR__ . '/../../fixtures/build_test_projects/updated_module/1.0.0'));
    $this->setReleaseMetadata([
      'updated_module' => __DIR__ . '/../../fixtures/release-history/updated_module.1.1.0.xml',
    ]);
    $this->runComposer('COMPOSER_MIRROR_PATH_REPOS=1 composer require drupal/alpha drupal/updated_module --update-with-all-dependencies', 'project');

    // The updated_module provides actual Drupal-facing functionality that we're
    // testing as well, so we need to install it.
    $this->installModules(['updated_module']);

    // Change both modules' upstream version.
    $this->addRepository('alpha', $this->copyFixtureToTempDirectory(__DIR__ . '/../../fixtures/build_test_projects/alpha/1.1.0'));
    $this->addRepository('updated_module', $this->copyFixtureToTempDirectory(__DIR__ . '/../../fixtures/build_test_projects/updated_module/1.1.0'));
    // Make .git folder

    // Use the API endpoint to create a stage and update updated_module to
    // 1.1.0. Even though both modules have version 1.1.0 available, only
    // updated_module should be updated. We ask the API to return the contents
    // of both modules' composer.json files, so we can assert that they were
    // updated to the versions we expect.
    // @see \Drupal\package_manager_test_api\ApiController::run()
    $file_contents = $this->getPackageManagerTestApiResponse(
      '/package-manager-test-api',
      [
        'runtime' => [
          'drupal/updated_module:1.1.0',
        ],
        'files_to_return' => [
          'web/modules/contrib/alpha/composer.json',
          'web/modules/contrib/updated_module/composer.json',
          'bravo.txt',
          "system_changes.json",
        ],
      ]
    );

    $expected_versions = [
      'alpha' => '1.0.0',
      'updated_module' => '1.1.0',
    ];
    foreach ($expected_versions as $module_name => $expected_version) {
      $path = "web/modules/contrib/$module_name/composer.json";
      $module_composer_json = json_decode($file_contents[$path]);
      $this->assertSame($expected_version, $module_composer_json->version);
    }
    // The post-apply event subscriber in updated_module 1.1.0 should have
    // created this file.
    // @see \Drupal\updated_module\PostApplySubscriber::postApply()
    $this->assertSame('Bravo!', $file_contents['bravo.txt']);

    $this->assertExpectedStageEventsFired(Stage::class);
  }

}

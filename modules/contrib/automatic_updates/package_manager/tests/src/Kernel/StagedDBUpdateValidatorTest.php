<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\ValidationResult;

/**
 * @covers \Drupal\package_manager\Validator\StagedDBUpdateValidator
 * @group package_manager
 * @internal
 */
class StagedDBUpdateValidatorTest extends PackageManagerKernelTestBase {

  /**
   * The extensions that will be used in this test.
   *
   * System and Stark are installed, so they are used to test what happens when
   * database updates are detected in installed extensions. Views and Olivero
   * are not installed by this test, so they are used to test what happens when
   * uninstalled extensions have database updates.
   *
   * @var string[]
   *
   * @see ::setUp()
   */
  private $extensions = [
    'system' => 'core/modules/system',
    'views' => 'core/modules/views',
    'stark' => 'core/themes/stark',
    'olivero' => 'core/themes/olivero',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['stark']);
    $this->assertFalse($this->container->get('module_handler')->moduleExists('views'));
    $this->assertFalse($this->container->get('theme_handler')->themeExists('olivero'));

    // Ensure that all the extensions we're testing with have database update
    // files in the active directory.
    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();

    foreach ($this->extensions as $extension_name => $extension_path) {
      $extension_path = $active_dir . '/' . $extension_path;
      mkdir($extension_path, 0777, TRUE);

      foreach ($this->providerSuffixes() as [$suffix]) {
        touch("$extension_path/$extension_name.$suffix");
      }
    }
  }

  /**
   * Data provider for several test methods.
   *
   * @return \string[][]
   *   The test cases.
   */
  public function providerSuffixes(): array {
    return [
      'hook_update_N' => ['install'],
      'hook_post_update_NAME' => ['post_update.php'],
    ];
  }

  /**
   * Tests that no errors are raised if the stage has no DB updates.
   */
  public function testNoUpdates(): void {
    $stage = $this->createStage();
    $stage->create();
    $this->assertStatusCheckResults([], $stage);
  }

  /**
   * Tests that a warning is raised if DB update files are removed in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileDeleted(string $suffix): void {
    $stage = $this->createStage();
    $stage->create();

    $stage_dir = $stage->getStageDirectory();
    foreach ($this->extensions as $name => $path) {
      unlink("$stage_dir/$path/$name.$suffix");
    }

    $result = ValidationResult::createWarning([t('System'), t('Stark')], t('Possible database updates have been detected in the following extensions.'));
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests that a warning is raised if DB update files are changed in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileChanged(string $suffix): void {
    $stage = $this->createStage();
    $stage->create();

    $stage_dir = $stage->getStageDirectory();
    foreach ($this->extensions as $name => $path) {
      file_put_contents("$stage_dir/$path/$name.$suffix", $this->randomString());
    }

    $result = ValidationResult::createWarning([t('System'), t('Stark')], t('Possible database updates have been detected in the following extensions.'));
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests that a warning is raised if DB update files are added in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileAdded(string $suffix): void {
    $stage = $this->createStage();
    $stage->create();

    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();
    foreach ($this->extensions as $name => $path) {
      unlink("$active_dir/$path/$name.$suffix");
    }

    $result = ValidationResult::createWarning([t('System'), t('Stark')], t('Possible database updates have been detected in the following extensions.'));
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests that the validator disregards unclaimed stages.
   */
  public function testUnclaimedStage(): void {
    $stage = $this->createStage();
    $stage->create();
    $this->assertStatusCheckResults([], $stage);
    // A new, unclaimed stage should be ignored by the validator.
    $this->assertStatusCheckResults([], $this->createStage());
  }

}

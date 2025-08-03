<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel\PathExcluder;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Drupal\package_manager\PathExcluder\GitExcluder
 * @group package_manager
 * @internal
 */
class GitExcluderTest extends PackageManagerKernelTestBase {

  /**
   * The mocked file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // In this test, we want to disable the lock file validator because, even
    // though both the active and stage directories will have a valid lock file,
    // this validator will complain because they don't differ at all.
    $this->disableValidators[] = 'package_manager.validator.lock_file';
    parent::setUp();
    $path_locator = $this->container->get('package_manager.path_locator');
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'foo/package_known_to_composer_removed_later',
        'type' => 'drupal-module',
        'version' => '1.0.0',
        'install_path' => "../../modules/module_known_to_composer_removed_later",
      ])
      ->addProjectAtPath("modules/module_not_known_to_composer_in_active")
      ->addDotGitFolder($path_locator->getProjectRoot() . "/modules/module_not_known_to_composer_in_active")
      ->addDotGitFolder($path_locator->getProjectRoot() . "/modules/module_known_to_composer_removed_later")
      ->commitChanges();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $this->fileSystem = $this->prophesize(FileSystemInterface::class);

    $container->getDefinition('package_manager.git_excluder')
      ->setArgument('$file_system', $this->fileSystem->reveal());
  }

  /**
   * Tests that Git directories are excluded from stage during PreCreate.
   */
  public function testGitDirectoriesExcludedActive(): void {
    // Ensure we have an up-to-date container.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $stage = $this->createStage();
    $stage->create();
    /** @var \Drupal\package_manager_bypass\BypassedStagerServiceBase $beginner */
    $beginner = $this->container->get('package_manager.beginner');
    $beginner_args = $beginner->getInvocationArguments();
    $excluded_paths = [
      '.git',
      'modules/module_not_known_to_composer_in_active/.git',
      'modules/example/.git',
    ];
    foreach ($excluded_paths as $excluded_path) {
      $this->assertContains($excluded_path, $beginner_args[0][2]->getAll());
    }
  }

  /**
   * Tests that Git directories are excluded from active during PreApply.
   */
  public function testGitDirectoriesExcludedStage(): void {
    // Ensure we have an up-to-date container.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $this->getStageFixtureManipulator()
      ->removePackage('foo/package_known_to_composer_removed_later');

    $stage = $this->createStage();
    $stage->create();
    $stage_dir = $stage->getStageDirectory();

    // Adding a module with .git in stage which is unknown to composer, we
    // expect it to not be copied to the active directory.
    $path = "$stage_dir/modules/unknown_to_composer_in_stage";
    $fs = new Filesystem();
    $fs->mkdir("$path/.git");
    file_put_contents(
      "$path/unknown_to_composer.info.yml",
      Yaml::encode([
        'name' => 'Unknown to composer in stage',
        'type' => 'module',
        'core_version_requirement' => '^9.3 || ^10',
      ])
    );
    file_put_contents("$path/.git/ignored.txt", 'Phoenix!');

    $stage->apply();
    /** @var \Drupal\package_manager_bypass\BypassedStagerServiceBase $committer */
    $committer = $this->container->get('package_manager.committer');
    $committer_args = $committer->getInvocationArguments();
    $excluded_paths = [
      '.git',
      'modules/module_not_known_to_composer_in_active/.git',
      'modules/example/.git',
    ];
    // We are missing "modules/unknown_to_composer_in_stage/.git" in excluded
    // paths because there is no validation for it as it is assumed about any
    // new .git folder in stage directory that either composer is aware of it or
    // the developer knows what they are doing.
    foreach ($excluded_paths as $excluded_path) {
      $this->assertContains($excluded_path, $committer_args[0][2]->getAll());
    }
    $this->assertNotContains('modules/unknown_to_composer_in_stage/.git', $committer_args[0][2]->getAll());
  }

}

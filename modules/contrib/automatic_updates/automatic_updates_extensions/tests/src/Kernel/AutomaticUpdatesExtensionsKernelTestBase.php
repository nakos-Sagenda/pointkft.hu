<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates_extensions\Kernel;

use Drupal\automatic_updates\Exception\UpdateException;
use Drupal\automatic_updates_extensions\ExtensionUpdater;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\package_manager\UnusedConfigFactory;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use Drupal\Tests\package_manager\Kernel\TestStageTrait;
use Drupal\Tests\package_manager\Kernel\TestStageValidationException;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactory;

/**
 * Base class for kernel tests of the Automatic Updates Extensions module.
 *
 * @internal
 */
abstract class AutomaticUpdatesExtensionsKernelTestBase extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates_extensions',
    'package_manager_test_release_history',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Disable the Composer executable validator, since it may cause the tests
    // to fail if a supported version of Composer is unavailable to the web
    // server. This should be okay in most situations because, apart from the
    // validator, only Composer Stager needs run Composer, and
    // package_manager_bypass is disabling those operations.
    $this->disableValidators[] = 'package_manager.validator.composer_executable';
    parent::setUp();
  }

  /**
   * Create Test Project.
   *
   * @param string|null $source_dir
   *   Source directory.
   */
  protected function createTestProject(?string $source_dir = NULL): void {
    $source_dir = $source_dir ?? __DIR__ . '/../../fixtures/fake-site';
    parent::createTestProject($source_dir);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Use the test-only implementations of the regular and cron updaters.
    $overrides = [
      'automatic_updates_extensions.updater' => TestExtensionUpdater::class,
    ];
    foreach ($overrides as $service_id => $class) {
      if ($container->hasDefinition($service_id)) {
        $container->getDefinition($service_id)->setClass($class);
      }
    }
  }

  /**
   * Asserts validation results are returned from a stage life cycle event.
   *
   * @param string[] $project_versions
   *   The project versions.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string|null $event_class
   *   (optional) The class of the event which should return the results. Must
   *   be passed if $expected_results is not empty.
   */
  protected function assertUpdateResults(array $project_versions, array $expected_results, string $event_class = NULL): void {
    $updater = $this->createExtensionUpdater();

    try {
      $updater->begin($project_versions);
      $updater->stage();
      $updater->apply();
      $updater->postApply();
      $updater->destroy();

      // If we did not get an exception, ensure we didn't expect any results.
      $this->assertEmpty($expected_results);
    }
    catch (TestStageValidationException $e) {
      $this->assertNotEmpty($expected_results);
      $this->assertValidationResultsEqual($expected_results, $e->getResults());
      // TestStage::dispatch() throws TestUpdateException with event object
      // so that we can analyze it.
      $this->assertInstanceOf(UpdateException::class, $e->getOriginalException());
      $this->assertNotEmpty($event_class);
      $this->assertInstanceOf($event_class, $e->getEvent());
    }
  }

  /**
   * Creates an extension updater object for testing purposes.
   *
   * @return \Drupal\Tests\automatic_updates_extensions\Kernel\TestExtensionUpdater
   *   A extension updater object, with test-only modifications.
   */
  protected function createExtensionUpdater(): TestExtensionUpdater {
    return new TestExtensionUpdater(
      // @todo Remove this in https://www.drupal.org/i/3303167
      new UnusedConfigFactory(),
      $this->container->get('package_manager.path_locator'),
      $this->container->get('package_manager.beginner'),
      $this->container->get('package_manager.stager'),
      $this->container->get('package_manager.committer'),
      $this->container->get('file_system'),
      $this->container->get('event_dispatcher'),
      $this->container->get('tempstore.shared'),
      $this->container->get('datetime.time'),
      new PathFactory(),
      $this->container->get('package_manager.failure_marker')
    );
  }

}

/**
 * A test-only version of the regular extension updater to override internals.
 */
class TestExtensionUpdater extends ExtensionUpdater {

  use TestStageTrait;

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Component\FileSystem\FileSystem as DrupalFileSystem;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\fixture_manipulator\StageFixtureManipulator;
use Drupal\KernelTests\KernelTestBase;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\StageEvent;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\UnusedConfigFactory;
use Drupal\package_manager\Validator\DiskSpaceValidator;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\Stage;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use Drupal\Tests\package_manager\Traits\FixtureManipulatorTrait;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;
use Drupal\Tests\package_manager\Traits\ValidationTestTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactory;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Base class for kernel tests of Package Manager's functionality.
 *
 * @internal
 */
abstract class PackageManagerKernelTestBase extends KernelTestBase {

  use AssertPreconditionsTrait;
  use FixtureManipulatorTrait;
  use FixtureUtilityTrait;
  use StatusCheckTrait;
  use ValidationTestTrait;

  /**
   * The mocked HTTP client that returns metadata about available updates.
   *
   * We need to preserve this as a class property so that we can re-inject it
   * into the container when a rebuild is triggered by module installation.
   *
   * @var \GuzzleHttp\Client
   *
   * @see ::register()
   */
  private $client;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'fixture_manipulator',
    'package_manager',
    'package_manager_bypass',
    'system',
    'update',
    'update_test',
  ];

  /**
   * The service IDs of any validators to disable.
   *
   * @var string[]
   */
  protected $disableValidators = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('package_manager');

    $this->createTestProject();

    // The Update module's default configuration must be installed for our
    // fake release metadata to be fetched, and the System module's to ensure
    // the site has a name.
    $this->installConfig(['system', 'update']);

    // Make the update system think that all of System's post-update functions
    // have run.
    $this->registerPostUpdateFunctions();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // If we previously set up a mock HTTP client in ::setReleaseMetadata(),
    // re-inject it into the container.
    if ($this->client) {
      $container->set('http_client', $this->client);
    }

    // When the test project is used, the disk space validator is replaced with
    // a mock. When staged changes are applied, the container is rebuilt, which
    // destroys the mocked service and can cause unexpected side effects. The
    // 'persist' tag prevents the mock from being destroyed during a container
    // rebuild.
    // @see ::createTestProject()
    $container->getDefinition('package_manager.validator.disk_space')
      ->addTag('persist');

    foreach ($this->disableValidators as $service_id) {
      if ($container->hasDefinition($service_id)) {
        $container->getDefinition($service_id)->clearTag('event_subscriber');
      }
    }
  }

  /**
   * Creates a stage object for testing purposes.
   *
   * @return \Drupal\Tests\package_manager\Kernel\TestStage
   *   A stage object, with test-only modifications.
   */
  protected function createStage(): TestStage {
    return new TestStage(
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

  /**
   * Asserts validation results are returned from a stage life cycle event.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string|null $event_class
   *   (optional) The class of the event which should return the results. Must
   *   be passed if $expected_results is not empty.
   *
   * @return \Drupal\package_manager\Stage
   *   The stage that was used to collect the validation results.
   */
  protected function assertResults(array $expected_results, string $event_class = NULL): Stage {
    $stage = $this->createStage();

    try {
      $stage->create();
      $stage->require(['drupal/core:9.8.1']);
      $stage->apply();
      $stage->postApply();
      $stage->destroy();

      // If we did not get an exception, ensure we didn't expect any results.
      $this->assertValidationResultsEqual([], $expected_results);
    }
    catch (TestStageValidationException $e) {
      $this->assertValidationResultsEqual($expected_results, $e->getResults());
      $this->assertNotEmpty($expected_results);
      // TestStage::dispatch() throws TestStageValidationException with the
      // event object so that we can analyze it.
      $this->assertNotEmpty($event_class);
      $this->assertInstanceOf(StageValidationException::class, $e->getOriginalException());
      $this->assertInstanceOf($event_class, $e->getEvent());
    }
    return $stage;
  }

  /**
   * Asserts validation results are returned from the status check event.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param \Drupal\Tests\package_manager\Kernel\TestStage|null $stage
   *   (optional) The test stage to use to create the status check event. If
   *   none is provided a new stage will be created.
   */
  protected function assertStatusCheckResults(array $expected_results, Stage $stage = NULL): void {
    $actual_results = $this->runStatusCheck($stage ?? $this->createStage(), $this->container->get('event_dispatcher'));
    $this->assertValidationResultsEqual($expected_results, $actual_results);
  }

  /**
   * Marks all pending post-update functions as completed.
   *
   * Since kernel tests don't normally install modules and register their
   * updates, this method makes sure that we are testing from a clean, fully
   * up-to-date state.
   */
  protected function registerPostUpdateFunctions(): void {
    $updates = $this->container->get('update.post_update_registry')
      ->getPendingUpdateFunctions();

    $this->container->get('keyvalue')
      ->get('post_update')
      ->set('existing_updates', $updates);
  }

  /**
   * Creates a test project.
   *
   * This will create a temporary uniques root directory and then creates two
   * directories in it:
   * 'active', which is the active directory containing a fake Drupal code base,
   * and 'stage', which is the root directory used to stage changes. The path
   * locator service will also be mocked so that it points to the test project.
   *
   * @param string|null $source_dir
   *   (optional) The path of a directory which should be copied into the
   *   test project and used as the active directory.
   */
  protected function createTestProject(?string $source_dir = NULL): void {
    static $called;
    if (isset($called)) {
      throw new \LogicException('Only one test project should be created per kernel test method!');
    }
    else {
      $called = TRUE;
    }

    $source_dir = $source_dir ?? __DIR__ . '/../../fixtures/fake_site';
    $root = DrupalFileSystem::getOsTemporaryDirectory() . DIRECTORY_SEPARATOR . 'package_manager_testing_root' . $this->databasePrefix;
    $fs = new Filesystem();
    if (is_dir($root)) {
      $fs->remove($root);
    }
    $fs->mkdir($root);

    // Create the active directory and copy its contents from a fixture.
    $active_dir = $root . DIRECTORY_SEPARATOR . 'active';
    $this->assertTrue(mkdir($active_dir));
    static::copyFixtureFilesTo($source_dir, $active_dir);

    // Make sure that the path repositories exist in the test project too.
    (new Filesystem())->mirror(__DIR__ . '/../../fixtures/path_repos', $root . DIRECTORY_SEPARATOR . 'path_repos', NULL, [
      'override' => TRUE,
      'delete' => FALSE,
    ]);

    // Removing 'vfs://root/' from site path set in
    // \Drupal\KernelTests\KernelTestBase::setUpFilesystem as we don't use vfs.
    $test_site_path = str_replace('vfs://root/', '', $this->siteDirectory);

    // Copy directory structure from vfs site directory to our site directory.
    (new Filesystem())->mirror($this->siteDirectory, $active_dir . DIRECTORY_SEPARATOR . $test_site_path);

    // Override siteDirectory to point to root/active/... instead of root/... .
    $this->siteDirectory = $active_dir . DIRECTORY_SEPARATOR . $test_site_path;

    // Override KernelTestBase::setUpFilesystem's Settings object.
    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['file_public_path'] = $this->siteDirectory . '/files';
    $settings['config_sync_directory'] = $this->siteDirectory . '/files/config/sync';
    new Settings($settings);

    // Create a stage root directory alongside the active directory.
    $staging_root = $root . DIRECTORY_SEPARATOR . 'stage';
    $this->assertTrue(mkdir($staging_root));

    // Ensure the path locator points to the test project. We assume that is its
    // own web root and the vendor directory is at its top level.
    /** @var \Drupal\package_manager_bypass\MockPathLocator $path_locator */
    $path_locator = $this->container->get('package_manager.path_locator');
    $path_locator->setPaths($active_dir, $active_dir . '/vendor', '', $staging_root);

    // This validator will persist through container rebuilds.
    // @see ::register()
    $validator = new TestDiskSpaceValidator(
      $path_locator,
      $this->container->get('string_translation')
    );
    // By default, the validator should report that the root, vendor, and
    // temporary directories have basically infinite free space.
    $validator->freeSpace = [
      $path_locator->getProjectRoot() => PHP_INT_MAX,
      $path_locator->getVendorDirectory() => PHP_INT_MAX,
      $validator->temporaryDirectory() => PHP_INT_MAX,
    ];
    $this->container->set('package_manager.validator.disk_space', $validator);
  }

  /**
   * Copies a fixture directory into the active directory.
   *
   * @param string $active_fixture_dir
   *   Path to fixture active directory from which the files will be copied.
   */
  protected function copyFixtureFolderToActiveDirectory(string $active_fixture_dir) {
    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();
    static::copyFixtureFilesTo($active_fixture_dir, $active_dir);
  }

  /**
   * Sets the current (running) version of core, as known to the Update module.
   *
   * @param string $version
   *   The current version of core.
   */
  protected function setCoreVersion(string $version): void {
    $this->config('update_test.settings')
      ->set('system_info.#all.version', $version)
      ->save();
  }

  /**
   * Sets the release metadata file to use when fetching available updates.
   *
   * @param string[] $files
   *   The paths of the XML metadata files to use, keyed by project name.
   */
  protected function setReleaseMetadata(array $files): void {
    $responses = [];

    foreach ($files as $project => $file) {
      $metadata = Utils::tryFopen($file, 'r');
      $responses["/release-history/$project/current"] = new Response(200, [], Utils::streamFor($metadata));
    }
    $callable = function (RequestInterface $request) use ($responses): Response {
      return $responses[$request->getUri()->getPath()] ?? new Response(404);
    };

    // The mock handler's queue consist of same callable as many times as the
    // number of requests we expect to be made for update XML because it will
    // retrieve one item off the queue for each request.
    // @see \GuzzleHttp\Handler\MockHandler::__invoke()
    $handler = new MockHandler(array_fill(0, 100, $callable));
    $this->client = new Client([
      'handler' => HandlerStack::create($handler),
    ]);
    $this->container->set('http_client', $this->client);
  }

  /**
   * Adds an event listener on an event for testing purposes.
   *
   * @param callable $listener
   *   The listener to add.
   * @param string $event_class
   *   (optional) The event to listen to. Defaults to PreApplyEvent.
   * @param int $priority
   *   (optional) The priority. Defaults to PHP_INT_MAX.
   */
  protected function addEventTestListener(callable $listener, string $event_class = PreApplyEvent::class, int $priority = PHP_INT_MAX): void {
    $this->container->get('event_dispatcher')
      ->addListener($event_class, $listener, $priority);
  }

  /**
   * Asserts event propagation is stopped by a certain event subscriber.
   *
   * @param string $event_class
   *   The event during which propagation is expected to stop.
   * @param callable $expected_propagation_stopper
   *   The event subscriber (which subscribes to the given event class) which is
   *   expected to stop propagation. This event subscriber must have been
   *   registered by one of the installed Drupal module.
   */
  protected function assertEventPropagationStopped(string $event_class, callable $expected_propagation_stopper): void {
    $priority = $this->container->get('event_dispatcher')->getListenerPriority($event_class, $expected_propagation_stopper);
    // Ensure the event subscriber was actually a listener for the event.
    $this->assertIsInt($priority);
    // Add a listener with a priority that is 1 less than priority of the
    // event subscriber. This listener would be called after
    // $expected_propagation_stopper if the event propagation was not stopped
    // and cause the test to fail.
    $this->addEventTestListener(function () use ($event_class): void {
      $this->fail('Event propagation should have been stopped during ' . $event_class . '.');
    }, $event_class, $priority - 1);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    StageFixtureManipulator::handleTearDown();
  }

}

/**
 * Test-only class to associate event with StageValidationException.
 *
 * @todo Remove this class in https://drupal.org/i/3331355 or if that issue is
 *   closed without adding the ability to associate events with exceptions
 *   remove this comment.
 */
final class TestStageValidationException extends StageValidationException {

  /**
   * The stage event.
   *
   * @var \Drupal\package_manager\Event\StageEvent
   */
  private $event;

  /**
   * The original exception.
   *
   * @var \Drupal\package_manager\Exception\StageValidationException
   */
  private $originalException;

  public function __construct(StageValidationException $original_exception, StageEvent $event) {
    parent::__construct($original_exception->getResults(), $original_exception->getMessage(), $original_exception->getCode(), $original_exception);
    $this->originalException = $original_exception;
    $this->event = $event;
  }

  /**
   * Gets the original exception which is triggered at the event.
   *
   * @return \Drupal\package_manager\Exception\StageValidationException
   *   Exception triggered at event.
   */
  public function getOriginalException(): StageValidationException {
    return $this->originalException;
  }

  /**
   * Gets the stage event which triggers the exception.
   *
   * @return \Drupal\package_manager\Event\StageEvent
   *   Event triggering stage exception.
   */
  public function getEvent(): StageEvent {
    return $this->event;
  }

}

/**
 * Common functions for test stages.
 */
trait TestStageTrait {

  /**
   * {@inheritdoc}
   */
  protected function dispatch(StageEvent $event, callable $on_error = NULL): void {
    try {
      parent::dispatch($event, $on_error);
    }
    catch (StageValidationException $e) {
      // Throw TestStageValidationException with event object so that test
      // code can verify that the exception was thrown when a specific event was
      // dispatched.
      throw new TestStageValidationException($e, $event);
    }
  }

}

/**
 * Defines a stage specifically for testing purposes.
 */
class TestStage extends Stage {

  use TestStageTrait;

  /**
   * {@inheritdoc}
   *
   * TRICKY: without this, any failed ::assertStatusCheckResults()
   * will fail, because PHPUnit will want to serialize all arguments in the call
   * stack.
   *
   * @see https://www.drupal.org/project/automatic_updates/issues/3312619#comment-14801308
   */
  public function __sleep(): array {
    return [];
  }

}

/**
 * A test version of the disk space validator to bypass system-level functions.
 */
class TestDiskSpaceValidator extends DiskSpaceValidator {

  /**
   * Whether the root and vendor directories are on the same logical disk.
   *
   * @var bool
   */
  public $sharedDisk = TRUE;

  /**
   * The amount of free space, keyed by path.
   *
   * @var float[]
   */
  public $freeSpace = [];

  /**
   * {@inheritdoc}
   */
  protected function stat(string $path): array {
    return [
      'dev' => $this->sharedDisk ? 'disk' : uniqid(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function freeSpace(string $path): float {
    return $this->freeSpace[$path];
  }

  /**
   * {@inheritdoc}
   */
  public function temporaryDirectory(): string {
    return 'temp';
  }

}

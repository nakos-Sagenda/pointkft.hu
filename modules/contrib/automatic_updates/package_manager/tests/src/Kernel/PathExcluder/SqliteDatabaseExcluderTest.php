<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel\PathExcluder;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathExcluder\SqliteDatabaseExcluder;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;

/**
 * @covers \Drupal\package_manager\PathExcluder\SqliteDatabaseExcluder
 * @group package_manager
 * @internal
 */
class SqliteDatabaseExcluderTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // In this test, we want to disable the lock file validator because, even
    // though both the active and stage directories will have a valid lock file,
    // this validator will complain because they don't differ at all.
    $this->disableValidators[] = 'package_manager.validator.lock_file';
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->getDefinition('package_manager.sqlite_excluder')
      ->setClass(TestSqliteDatabaseExcluder::class);
  }

  /**
   * Mocks a SQLite database connection for the event subscriber.
   *
   * @param array $connection_options
   *   The connection options for the mocked database connection.
   */
  private function mockDatabase(array $connection_options): void {
    $database = $this->prophesize(Connection::class);
    $database->driver()->willReturn('sqlite');
    $database->getConnectionOptions()->willReturn($connection_options);

    /** @var \Drupal\Tests\package_manager\Kernel\PathExcluder\TestSiteConfigurationExcluder $sqlite_excluder */
    $sqlite_excluder = $this->container->get('package_manager.sqlite_excluder');
    $sqlite_excluder->database = $database->reveal();
  }

  /**
   * Tests that SQLite database files are excluded from stage operations.
   */
  public function testSqliteDatabaseFilesExcluded(): void {
    // In this test, we want to perform the actual stage operations so that we
    // can be sure that files are staged as expected.
    $this->setSetting('package_manager_bypass_composer_stager', FALSE);
    // Ensure we have an up-to-date container.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();

    // Mock a SQLite database connection to a file in the active directory. The
    // file should not be staged.
    $this->mockDatabase([
      'database' => 'sites/example.com/db.sqlite',
    ]);

    $stage = $this->createStage();
    $stage->create();
    $stage_dir = $stage->getStageDirectory();

    $ignored = [
      "sites/example.com/db.sqlite",
      "sites/example.com/db.sqlite-shm",
      "sites/example.com/db.sqlite-wal",
    ];
    foreach ($ignored as $path) {
      $this->assertFileExists("$active_dir/$path");
      $this->assertFileDoesNotExist("$stage_dir/$path");
    }

    $stage->apply();
    // The ignored files should still be in the active directory.
    foreach ($ignored as $path) {
      $this->assertFileExists("$active_dir/$path");
    }
  }

  /**
   * Data provider for testPathProcessing().
   *
   * @return string[][]
   *   The test cases.
   */
  public function providerPathProcessing(): array {
    return [
      'relative path, in site directory' => [
        'sites/example.com/db.sqlite',
        [
          'sites/example.com/db.sqlite',
          'sites/example.com/db.sqlite-shm',
          'sites/example.com/db.sqlite-wal',
        ],
      ],
      'relative path, at root' => [
        'db.sqlite',
        [
          'db.sqlite',
          'db.sqlite-shm',
          'db.sqlite-wal',
        ],
      ],
      'absolute path, in site directory' => [
        '/sites/example.com/db.sqlite',
        [
          'sites/example.com/db.sqlite',
          'sites/example.com/db.sqlite-shm',
          'sites/example.com/db.sqlite-wal',
        ],
      ],
      'absolute path, at root' => [
        '/db.sqlite',
        [
          'db.sqlite',
          'db.sqlite-shm',
          'db.sqlite-wal',
        ],
      ],
    ];
  }

  /**
   * Tests SQLite database path processing.
   *
   * This test ensures that SQLite database paths are processed properly (e.g.,
   * converting an absolute path to a relative path) before being flagged for
   * exclusion.
   *
   * @param string $database_path
   *   The path of the SQLite database, as set in the database connection
   *   options. If it begins with a slash, it will be prefixed with the path of
   *   the active directory.
   * @param string[] $expected_exclusions
   *   The database paths which should be flagged for exclusion.
   *
   * @dataProvider providerPathProcessing
   */
  public function testPathProcessing(string $database_path, array $expected_exclusions): void {
    // If the database path should be treated as absolute, prefix it with the
    // path of the active directory.
    if (str_starts_with($database_path, '/')) {
      $database_path = $this->container->get('package_manager.path_locator')->getProjectRoot() . $database_path;
    }
    $this->mockDatabase([
      'database' => $database_path,
    ]);

    $event = new CollectIgnoredPathsEvent($this->createStage());
    // Invoke the event subscriber directly, so we can check that the database
    // was correctly excluded.
    $this->container->get('package_manager.sqlite_excluder')
      ->excludeDatabaseFiles($event);
    // All of the expected exclusions should be flagged.
    $this->assertEmpty(array_diff($expected_exclusions, $event->getAll()));
  }

}

/**
 * A test-only version of the SQLite database excluder, to expose internals.
 */
class TestSqliteDatabaseExcluder extends SqliteDatabaseExcluder {

  /**
   * {@inheritdoc}
   */
  public $database;

}

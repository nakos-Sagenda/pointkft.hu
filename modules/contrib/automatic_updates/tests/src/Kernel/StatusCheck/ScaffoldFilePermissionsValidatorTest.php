<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @covers \Drupal\automatic_updates\Validator\ScaffoldFilePermissionsValidator
 * @group automatic_updates
 * @internal
 */
class ScaffoldFilePermissionsValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * The active directory of the test project.
   *
   * @var string
   */
  private $activeDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->activeDir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();
  }

  /**
   * {@inheritdoc}
   */
  protected function assertValidationResultsEqual(array $expected_results, array $actual_results, ?PathLocator $path_locator = NULL, ?string $stage_dir = NULL): void {
    $map = function (string $path): string {
      return $this->activeDir . '/' . $path;
    };
    foreach ($expected_results as $i => $result) {
      // Prepend the active directory to every path listed in the error result,
      // and add the expected summary.
      $messages = array_map($map, $result->getMessages());
      $expected_results[$i] = ValidationResult::createError($messages, t('The following paths must be writable in order to update default site configuration files.'));
    }
    parent::assertValidationResultsEqual($expected_results, $actual_results, $path_locator);
  }

  /**
   * Write-protects a set of paths in the active directory.
   *
   * @param string[] $paths
   *   The paths to write-protect, relative to the active directory.
   */
  private function writeProtect(array $paths): void {
    foreach ($paths as $path) {
      $path = $this->activeDir . '/' . $path;
      chmod($path, 0500);
      $this->assertFileIsNotWritable($path, "Failed to write-protect $path.");
    }
  }

  /**
   * Data provider for testPermissionsBeforeStart().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerPermissionsBeforeStart(): array {
    return [
      'write-protected scaffold file, writable site directory' => [
        ['sites/default/default.settings.php'],
        [
          ValidationResult::createError([t('sites/default/default.settings.php')]),
        ],
      ],
      // Whether the site directory is write-protected only matters during
      // pre-apply, because it only presents a problem if scaffold files have
      // been added or removed in the stage directory. Which is a condition we
      // can only detect during pre-apply.
      'write-protected scaffold file and site directory' => [
        [
          'sites/default/default.settings.php',
          'sites/default',
        ],
        [
          ValidationResult::createError([t('sites/default/default.settings.php')]),
        ],
      ],
      'write-protected site directory' => [
        ['sites/default'],
        [],
      ],
    ];
  }

  /**
   * Tests that scaffold file permissions are checked before an update begins.
   *
   * @param string[] $write_protected_paths
   *   A list of paths, relative to the project root, which should be write
   *   protected before staged changes are applied.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results, if any.
   *
   * @dataProvider providerPermissionsBeforeStart
   */
  public function testPermissionsBeforeStart(array $write_protected_paths, array $expected_results): void {
    $this->writeProtect($write_protected_paths);
    $this->assertCheckerResultsFromManager($expected_results, TRUE);

    try {
      $this->container->get('automatic_updates.updater')
        ->begin(['drupal' => '9.8.1']);

      // If no exception was thrown, ensure that we weren't expecting an error.
      $this->assertEmpty($expected_results);
    }
    catch (StageValidationException $e) {
      $this->assertValidationResultsEqual($expected_results, $e->getResults());
    }
  }

  /**
   * Data provider for testScaffoldFilesChanged().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerScaffoldFilesChanged(): array {
    // The summary is always replaced by ::assertValidationResultsEqual(), so
    // if there's more than one message in a result, just give it a mocked
    // summary object to prevent an exception.
    $summary = $this->prophesize('\Drupal\Core\StringTranslation\TranslatableMarkup')
      ->reveal();

    return [
      // If no scaffold files are changed, it doesn't matter if the site
      // directory is writable.
      'no scaffold changes, site directory not writable' => [
        ['sites/default'],
        [],
        [],
        [],
      ],
      'no scaffold changes, site directory writable' => [
        [],
        [],
        [],
        [],
      ],
      // If scaffold files are added or deleted in the site directory, the site
      // directory must be writable.
      'new scaffold file added to non-writable site directory' => [
        ['sites/default'],
        [],
        [
          '[web-root]/sites/default/new.txt' => '',
        ],
        [
          ValidationResult::createError([t('sites/default')]),
        ],
      ],
      'new scaffold file added to writable site directory' => [
        [],
        [],
        [
          '[web-root]/sites/default/new.txt' => '',
        ],
        [],
      ],
      'writable scaffold file removed from non-writable site directory' => [
        ['sites/default'],
        [
          '[web-root]/sites/default/deleted.txt' => '',
        ],
        [],
        [
          ValidationResult::createError([t('sites/default')]),
        ],
      ],
      'writable scaffold file removed from writable site directory' => [
        [],
        [
          '[web-root]/sites/default/deleted.txt' => '',
        ],
        [],
        [],
      ],
      'non-writable scaffold file removed from non-writable site directory' => [
        [
          // The file must be made write-protected before the site directory is,
          // or the permissions change will fail.
          'sites/default/deleted.txt',
          'sites/default',
        ],
        [
          '[web-root]/sites/default/deleted.txt' => '',
        ],
        [],
        [
          ValidationResult::createError([
            t('sites/default'),
            t('sites/default/deleted.txt'),
          ], $summary),
        ],
      ],
      'non-writable scaffold file removed from writable site directory' => [
        ['sites/default/deleted.txt'],
        [
          '[web-root]/sites/default/deleted.txt' => '',
        ],
        [],
        [
          ValidationResult::createError([t('sites/default/deleted.txt')]),
        ],
      ],
      // If only scaffold files outside the site directory changed, the
      // validator doesn't care if the site directory is writable.
      'new scaffold file added outside non-writable site directory' => [
        ['sites/default'],
        [],
        [
          '[web-root]/foo.html' => '',
        ],
        [],
      ],
      'new scaffold file added outside writable site directory' => [
        [],
        [],
        [
          '[web-root]/foo.html' => '',
        ],
        [],
      ],
      'writable scaffold file removed outside non-writable site directory' => [
        ['sites/default'],
        [
          '[web-root]/foo.txt' => '',
        ],
        [],
        [],
      ],
      'writable scaffold file removed outside writable site directory' => [
        [],
        [
          '[web-root]/foo.txt' => '',
        ],
        [],
        [],
      ],
      'non-writable scaffold file removed outside non-writable site directory' => [
        [
          'sites/default',
          'foo.txt',
        ],
        [
          '[web-root]/foo.txt' => '',
        ],
        [],
        [],
      ],
      'non-writable scaffold file removed outside writable site directory' => [
        ['foo.txt'],
        [
          '[web-root]/foo.txt' => '',
        ],
        [],
        [],
      ],
    ];
  }

  /**
   * Tests site directory permissions are checked before changes are applied.
   *
   * @param string[] $write_protected_paths
   *   A list of paths, relative to the project root, which should be write
   *   protected before staged changes are applied.
   * @param string[] $active_scaffold_files
   *   An array simulating the extra.drupal-scaffold.file-mapping section of the
   *   active drupal/core package.
   * @param string[] $staged_scaffold_files
   *   An array simulating the extra.drupal-scaffold.file-mapping section of the
   *   staged drupal/core package.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results, if any.
   *
   * @dataProvider providerScaffoldFilesChanged
   */
  public function testScaffoldFilesChanged(array $write_protected_paths, array $active_scaffold_files, array $staged_scaffold_files, array $expected_results): void {
    // Rewrite the active and staged installed.json files, inserting the given
    // lists of scaffold files.
    (new ActiveFixtureManipulator())
      ->modifyPackage('drupal/core', [
        'extra' => [
          'drupal-scaffold' => [
            'file-mapping' => $active_scaffold_files,
          ],
        ],
      ])
      ->commitChanges();
    $this->getStageFixtureManipulator()
      ->setCorePackageVersion('9.8.1')
      ->modifyPackage('drupal/core', [
        'extra' => [
          'drupal-scaffold' => [
            'file-mapping' => $staged_scaffold_files,
          ],
        ],
      ]);

    // Create fake scaffold files so we can test scenarios in which a scaffold
    // file that exists in the active directory is deleted in the stage
    // directory.
    touch($this->activeDir . '/sites/default/deleted.txt');
    touch($this->activeDir . '/foo.txt');

    $updater = $this->container->get('automatic_updates.updater');
    $updater->begin(['drupal' => '9.8.1']);
    $updater->stage();

    $this->writeProtect($write_protected_paths);

    try {
      $updater->apply();

      // If no exception was thrown, ensure that we weren't expecting an error.
      $this->assertEmpty($expected_results);
    }
    catch (StageValidationException $e) {
      $this->assertValidationResultsEqual($expected_results, $e->getResults());
    }
  }

}

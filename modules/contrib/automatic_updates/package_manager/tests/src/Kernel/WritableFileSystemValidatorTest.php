<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\ValidationResult;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests the file system permissions validator.
 *
 * This validator is tested functionally in Automatic Updates' build tests,
 * since those give us control over the file system permissions.
 *
 * @see \Drupal\Tests\automatic_updates\Build\CoreUpdateTest::assertReadOnlyFileSystemError()
 *
 * @covers \Drupal\package_manager\Validator\WritableFileSystemValidator
 * @group package_manager
 * @internal
 */
class WritableFileSystemValidatorTest extends PackageManagerKernelTestBase {

  /**
   * Data provider for testWritable().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerWritable(): array {
    // @see \Drupal\Tests\package_manager\Traits\ValidationTestTrait::resolvePlaceholdersInArrayValuesWithRealPaths()
    $root_error = t('The Drupal directory "<PROJECT_ROOT>" is not writable.');
    $vendor_error = t('The vendor directory "<VENDOR_DIR>" is not writable.');
    $summary = t('The file system is not writable.');
    $writable_permission = 0777;
    $non_writable_permission = 0550;

    return [
      'root and vendor are writable' => [
        $writable_permission,
        $writable_permission,
        [],
      ],
      'root writable, vendor not writable' => [
        $writable_permission,
        $non_writable_permission,
        [
          ValidationResult::createError([$vendor_error], $summary),
        ],
      ],
      'root not writable, vendor writable' => [
        $non_writable_permission,
        $writable_permission,
        [
          ValidationResult::createError([$root_error], $summary),
        ],
      ],
      'nothing writable' => [
        $non_writable_permission,
        $non_writable_permission,
        [
          ValidationResult::createError([$root_error, $vendor_error], $summary),
        ],
      ],
    ];
  }

  /**
   * Tests the file system permissions validator.
   *
   * @param int $root_permissions
   *   The file permissions for the root folder.
   * @param int $vendor_permissions
   *   The file permissions for the vendor folder.
   * @param array $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerWritable
   */
  public function testWritable(int $root_permissions, int $vendor_permissions, array $expected_results): void {
    $path_locator = $this->container->get('package_manager.path_locator');

    // We need to set the vendor directory's permissions first because, in the
    // test project, it's located inside the project root.
    $this->assertTrue(chmod($path_locator->getVendorDirectory(), $vendor_permissions));
    $this->assertTrue(chmod($path_locator->getProjectRoot(), $root_permissions));

    $this->assertStatusCheckResults($expected_results);
    $this->assertResults($expected_results, PreCreateEvent::class);
  }

  /**
   * Tests the file system permissions validator during pre-apply.
   *
   * @param int $root_permissions
   *   The file permissions for the root folder.
   * @param int $vendor_permissions
   *   The file permissions for the vendor folder.
   * @param array $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerWritable
   */
  public function testWritableDuringPreApply(int $root_permissions, int $vendor_permissions, array $expected_results): void {
    $this->addEventTestListener(
      function () use ($root_permissions, $vendor_permissions): void {
        $path_locator = $this->container->get('package_manager.path_locator');

        // We need to set the vendor directory's permissions first because, in
        // the test project, it's located inside the project root.
        $this->assertTrue(chmod($path_locator->getVendorDirectory(), $vendor_permissions));
        $this->assertTrue(chmod($path_locator->getProjectRoot(), $root_permissions));

        // During pre-apply we don't care whether the staging root is writable.
        $this->assertTrue(chmod($path_locator->getStagingRoot(), 0550));
      },
    );

    $this->assertResults($expected_results, PreApplyEvent::class);
  }

  /**
   * Data provider for ::testStagingRootPermissions().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerStagingRootPermissions(): array {
    $writable_permission = 0777;
    $non_writable_permission = 0550;
    $summary = t('The file system is not writable.');
    return [
      'writable stage root exists' => [
        $writable_permission,
        [],
        FALSE,
      ],
      'write-protected stage root exists' => [
        $non_writable_permission,
        [
          ValidationResult::createError(['The stage root directory "<STAGE_ROOT>" is not writable.'], $summary),
        ],
        FALSE,
      ],
      'stage root directory does not exist, parent directory not writable' => [
        $non_writable_permission,
        [
          ValidationResult::createError(['The stage root directory will not able to be created at "<STAGE_ROOT_PARENT>".'], $summary),
        ],
        TRUE,
      ],
    ];
  }

  /**
   * Tests that the stage root's permissions are validated.
   *
   * @param int $permissions
   *   The file permissions to apply to the stage root directory, or its parent
   *   directory, depending on the value of $delete_staging_root.
   * @param array $expected_results
   *   The expected validation results.
   * @param bool $delete_staging_root
   *   Whether the stage root directory will exist at all.
   *
   * @dataProvider providerStagingRootPermissions
   */
  public function testStagingRootPermissions(int $permissions, array $expected_results, bool $delete_staging_root): void {
    $dir = $this->container->get('package_manager.path_locator')
      ->getStagingRoot();

    if ($delete_staging_root) {
      $fs = new Filesystem();
      $fs->remove($dir);
      $dir = dirname($dir);
    }
    $this->assertTrue(chmod($dir, $permissions));
    $this->assertStatusCheckResults($expected_results);
    $this->assertResults($expected_results, PreCreateEvent::class);
  }

}

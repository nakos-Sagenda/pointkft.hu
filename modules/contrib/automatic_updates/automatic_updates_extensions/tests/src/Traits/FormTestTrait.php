<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates_extensions\Traits;

use Behat\Mink\WebAssert;

/**
 * Common methods for testing the update form.
 *
 * @internal
 */
trait FormTestTrait {

  /**
   * Asserts the table shows the updates.
   *
   * @param \Behat\Mink\WebAssert $assert
   *   The web assert tool.
   * @param string $expected_project_title
   *   The expected project title.
   * @param string $expected_installed_version
   *   The expected installed version.
   * @param string $expected_target_version
   *   The expected target version.
   * @param int $row
   *   The row number.
   */
  private function assertUpdateTableRow(WebAssert $assert, string $expected_project_title, string $expected_installed_version, string $expected_target_version, int $row = 1): void {
    $row_selector = ".update-recommended tr:nth-of-type($row)";
    $assert->elementTextContains('css', $row_selector . ' td:nth-of-type(2)', $expected_project_title);
    $assert->elementTextContains('css', $row_selector . ' td:nth-of-type(3)', $expected_installed_version);
    $assert->elementTextContains('css', $row_selector . ' td:nth-of-type(4)', $expected_target_version);
  }

  /**
   * Asserts the table shows the expected number of updates.
   *
   * @param int $expected_update_count
   *   The no of rows in table.
   */
  protected function assertUpdatesCount(int $expected_update_count): void {
    $this->assertSession()->elementsCount('css', '.update-recommended tbody tr', $expected_update_count);
  }

}

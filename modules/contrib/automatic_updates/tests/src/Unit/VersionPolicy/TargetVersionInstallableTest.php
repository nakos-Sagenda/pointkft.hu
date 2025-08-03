<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionInstallable;
use Drupal\update\ProjectRelease;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionInstallable
 * @group automatic_updates
 * @internal
 */
class TargetVersionInstallableTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testTargetVersionInstallable().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerTargetVersionInstallable(): array {
    return [
      'no available releases' => [
        [],
        ['Cannot update Drupal core to 9.8.2 because it is not in the list of installable releases.'],
      ],
      'unknown target' => [
        [
          '9.8.1' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-8-1-release',
            'version' => '9.8.1',
          ]),
        ],
        ['Cannot update Drupal core to 9.8.2 because it is not in the list of installable releases.'],
      ],
      'valid target' => [
        [
          '9.8.2' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-8-2-release',
            'version' => '9.8.2',
          ]),
        ],
        [],
      ],
    ];
  }

  /**
   * Tests that the target version must be a known, installable release.
   *
   * @param \Drupal\update\ProjectRelease[] $available_releases
   *   The available releases of Drupal core, keyed by version.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerTargetVersionInstallable
   */
  public function testTargetVersionInstallable(array $available_releases, array $expected_errors): void {
    $rule = new TargetVersionInstallable();
    $this->assertPolicyErrors($rule, '9.8.1', '9.8.2', $expected_errors, $available_releases);
  }

}

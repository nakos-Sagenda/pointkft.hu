<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\MinorUpdatesEnabled;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\MinorUpdatesEnabled
 * @group automatic_updates
 * @internal
 */
class MinorUpdatesEnabledTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testMinorUpdatesEnabled().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerMinorUpdatesEnabled(): array {
    return [
      'same versions, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '9.8.0',
        [],
      ],
      'same versions, minor updates allowed' => [
        TRUE,
        '9.8.0',
        '9.8.0',
        [],
      ],
      'target version newer in same minor, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '9.8.2',
        [],
      ],
      'target version newer in same minor, minor updates allowed' => [
        TRUE,
        '9.8.0',
        '9.8.2',
        [],
      ],
      'target version in newer minor, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '9.9.2',
        ['Drupal cannot be automatically updated from 9.8.0 to 9.9.2 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in newer minor, minor updates allowed' => [
        TRUE,
        '9.8.0',
        '9.9.2',
        [],
      ],
      'target version older in same minor, minor updates forbidden' => [
        FALSE,
        '9.8.2',
        '9.8.0',
        [],
      ],
      'target version older in same minor, minor updates allowed' => [
        TRUE,
        '9.8.2',
        '9.8.0',
        [],
      ],
      'target version in older minor, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '9.7.2',
        ['Drupal cannot be automatically updated from 9.8.0 to 9.7.2 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in older minor, minor updates allowed' => [
        TRUE,
        '9.8.0',
        '9.7.2',
        [],
      ],
      'target version in older major, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '8.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 8.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in older major, minor updates allowed' => [
        FALSE,
        '9.8.0',
        '8.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 8.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in newer major, minor updates forbidden' => [
        FALSE,
        '9.8.0',
        '10.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 10.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in newer major, minor updates allowed' => [
        FALSE,
        '9.8.0',
        '10.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 10.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
    ];
  }

  /**
   * Tests that trying to update across minor versions depends on configuration.
   *
   * @param bool $allowed
   *   Whether or not updating across minor versions is allowed.
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if not known.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerMinorUpdatesEnabled
   */
  public function testMinorUpdatesEnabled(bool $allowed, string $installed_version, ?string $target_version, array $expected_errors): void {
    $config_factory = $this->getConfigFactoryStub([
      'automatic_updates.settings' => [
        'allow_core_minor_updates' => $allowed,
      ],
    ]);
    $rule = new MinorUpdatesEnabled($config_factory);
    $this->assertPolicyErrors($rule, $installed_version, $target_version, $expected_errors);
  }

}

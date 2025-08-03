<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdater;
use Drupal\automatic_updates\Validator\CronFrequencyValidator;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @covers \Drupal\automatic_updates\Validator\CronFrequencyValidator
 * @group automatic_updates
 * @internal
 */
class CronFrequencyValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // In this test, we do not want to do an update. We're just testing that
    // cron is configured to run frequently enough to do automatic updates. So,
    // pretend we're already on the latest secure version of core.
    $this->setCoreVersion('9.8.1');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
  }

  /**
   * Tests that nothing is validated if updates are disabled during cron.
   */
  public function testNoValidationIfCronDisabled(): void {
    $this->config('automatic_updates.settings')
      ->set('cron', CronUpdater::DISABLED)
      ->save();

    $validator = new class (
      $this->container->get('config.factory'),
      $this->container->get('module_handler'),
      $this->container->get('state'),
      $this->container->get('datetime.time'),
      $this->container->get('string_translation'),
      $this->container->get('automatic_updates.cron_updater'),
      $this->container->get('lock')
    ) extends CronFrequencyValidator {

      /**
       * {@inheritdoc}
       */
      protected function validateAutomatedCron($event): void {
        throw new AssertionFailedError(__METHOD__ . '() should not have been called.');
      }

      /**
       * {@inheritdoc}
       */
      protected function validateLastCronRun($event): void {
        throw new AssertionFailedError(__METHOD__ . '() should not have been called.');
      }

    };
    $this->container->set('automatic_updates.cron_frequency_validator', $validator);
    $this->assertCheckerResultsFromManager([], TRUE);
  }

  /**
   * Data provider for testLastCronRunValidation().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerLastCronRunValidation(): array {
    $error = ValidationResult::createError([
      t('Cron has not run recently. For more information, see the online handbook entry for <a href="https://www.drupal.org/cron">configuring cron jobs</a> to run at least every 3 hours.'),
    ]);

    return [
      'cron never ran' => [
        0,
        [$error],
      ],
      'cron ran four hours ago' => [
        time() - 14400,
        [$error],
      ],
      'cron ran an hour ago' => [
        time() - 3600,
        [],
      ],
    ];
  }

  /**
   * Tests validation based on the last cron run time.
   *
   * @param int $last_run
   *   A timestamp of the last time cron ran.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerLastCronRunValidation
   */
  public function testLastCronRunValidation(int $last_run, array $expected_results): void {
    $this->container->get('state')->set('system.cron_last', $last_run);
    $this->assertCheckerResultsFromManager($expected_results, TRUE);

    // After running cron, any errors or warnings should be gone.
    $this->container->get('cron')->run();
    $this->assertCheckerResultsFromManager([], TRUE);
  }

  /**
   * Data provider for testAutomatedCronValidation().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerAutomatedCronValidation(): array {
    return [
      'default configuration' => [
        NULL,
        [],
      ],
      'every 6 hours' => [
        21600,
        [
          ValidationResult::createWarning([
            t('Cron is not set to run frequently enough. <a href="/admin/config/system/cron">Configure it</a> to run at least every 3 hours or disable automated cron and run it via an external scheduling system.'),
          ]),
        ],
      ],
      'every 25 hours' => [
        90000,
        [
          ValidationResult::createError([
            t('Cron is not set to run frequently enough. <a href="/admin/config/system/cron">Configure it</a> to run at least every 3 hours or disable automated cron and run it via an external scheduling system.'),
          ]),
        ],
      ],
    ];
  }

  /**
   * Tests validation based on Automated Cron settings.
   *
   * @param int|null $interval
   *   The configured interval for Automated Cron. If NULL, the default value
   *   will be used.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerAutomatedCronValidation
   */
  public function testAutomatedCronValidation(?int $interval, array $expected_results): void {
    $this->enableModules(['automated_cron']);
    $this->installConfig('automated_cron');

    if (isset($interval)) {
      $this->config('automated_cron.settings')
        ->set('interval', $interval)
        ->save();
    }
    $this->assertCheckerResultsFromManager($expected_results, TRUE);

    // Even after running cron, we should have the same results.
    $this->container->get('cron')->run();
    $this->assertCheckerResultsFromManager($expected_results);
  }

}

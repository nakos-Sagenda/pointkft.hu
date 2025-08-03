<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdater;
use Drupal\automatic_updates\Validator\CronServerValidator;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Tests\package_manager\Traits\PackageManagerBypassTestTrait;

/**
 * @covers \Drupal\automatic_updates\Validator\CronServerValidator
 * @group automatic_updates
 * @internal
 */
class CronServerValidatorTest extends AutomaticUpdatesKernelTestBase {

  use PackageManagerBypassTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Data provider for ::testCronServerValidation().
   *
   * @return array[]
   *   Sets of arguments to pass to the test method.
   */
  public function providerCronServerValidation(): array {
    $error = ValidationResult::createError([
      t('Your site appears to be running on the built-in PHP web server on port 80. Drupal cannot be automatically updated with this configuration unless the site can also be reached on an alternate port.'),
    ]);
    // Add all the test cases where there no expected results for all cron
    // modes.
    foreach ([CronUpdater::DISABLED, CronUpdater::SECURITY, CronUpdater::ALL] as $cron_mode) {
      $test_cases["PHP server with alternate port, cron $cron_mode"] = [
        TRUE,
        'cli-server',
        $cron_mode,
        [],
      ];
      $test_cases[" 'other server with alternate port, cron $cron_mode"] = [
        TRUE,
        'nginx',
        $cron_mode,
        [],
      ];
      $test_cases["other server with same port, cron $cron_mode"] = [
        FALSE,
        'nginx',
        $cron_mode,
        [],
      ];
    }
    // If the PHP server is used with the same port and cron is enabled an error
    // will be flagged.
    foreach ([CronUpdater::SECURITY, CronUpdater::ALL] as $cron_mode) {
      $test_cases["PHP server with same port, cron $cron_mode"] = [
        FALSE,
        'cli-server',
        $cron_mode,
        [$error],
      ];
    }
    $test_cases["PHP server with same port, cron disabled"] = [
      FALSE,
      'cli-server',
      CronUpdater::DISABLED,
      [],
    ];
    return $test_cases;
  }

  /**
   * Tests server validation during pre-create for unattended updates.
   *
   * @param bool $alternate_port
   *   Whether or not an alternate port should be set.
   * @param string $server_api
   *   The value of the PHP_SAPI constant, as known to the validator.
   * @param string $cron_mode
   *   The cron mode to test with. Can be any of
   *   \Drupal\automatic_updates\CronUpdater::DISABLED,
   *   \Drupal\automatic_updates\CronUpdater::SECURITY, or
   *   \Drupal\automatic_updates\CronUpdater::ALL.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerCronServerValidation
   */
  public function testCronServerValidationDuringPreCreate(bool $alternate_port, string $server_api, string $cron_mode, array $expected_results): void {
    // If CronUpdater is disabled, a stage will never be created; nor will it if
    // validation results happen before the stage is even created: in either
    // case the stage fixture need not be manipulated.
    if ($cron_mode !== CronUpdater::DISABLED && empty($expected_results)) {
      $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    }
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $this->assertNotEmpty($request);
    $this->assertSame(80, $request->getPort());

    $property = new \ReflectionProperty(CronServerValidator::class, 'serverApi');
    $property->setAccessible(TRUE);
    $property->setValue(NULL, $server_api);

    $this->config('automatic_updates.settings')
      ->set('cron', $cron_mode)
      ->set('cron_port', $alternate_port ? 2501 : 0)
      ->save();

    $this->assertCheckerResultsFromManager($expected_results, TRUE);

    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);

    // If errors were expected, cron should not have run.
    $this->container->get('cron')->run();
    if ($expected_results) {
      // Assert the update was not staged to ensure the error was flagged in
      // PreCreateEvent and not PreApplyEvent.
      $this->assertUpdateStagedTimes(0);
      $error = new StageValidationException($expected_results);
      $this->assertTrue($logger->hasRecord($error->getMessage(), (string) RfcLogLevel::ERROR));
    }
    else {
      $this->assertFalse($logger->hasRecords((string) RfcLogLevel::ERROR));
    }
  }

  /**
   * Tests server validation during pre-apply for unattended updates.
   *
   * @param bool $alternate_port
   *   Whether or not an alternate port should be set.
   * @param string $server_api
   *   The value of the PHP_SAPI constant, as known to the validator.
   * @param string $cron_mode
   *   The cron mode to test with. Can be any of
   *   \Drupal\automatic_updates\CronUpdater::DISABLED,
   *   \Drupal\automatic_updates\CronUpdater::SECURITY, or
   *   \Drupal\automatic_updates\CronUpdater::ALL.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerCronServerValidation
   */
  public function testCronServerValidationDuringPreApply(bool $alternate_port, string $server_api, string $cron_mode, array $expected_results): void {
    // If CronUpdater is disabled, a stage will never be created, hence
    // stage fixture need not be manipulated.
    if ($cron_mode !== CronUpdater::DISABLED) {
      $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    }
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $this->assertNotEmpty($request);
    $this->assertSame(80, $request->getPort());

    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);

    $this->config('automatic_updates.settings')
      ->set('cron', $cron_mode)
      ->save();

    // Add a listener to change the $server_api and $alternate_port settings
    // during PreApplyEvent. We set $cron_mode above because this determines
    // whether updates will actually be run in cron.
    $this->addEventTestListener(
      function () use ($alternate_port, $server_api): void {
        $property = new \ReflectionProperty(CronServerValidator::class, 'serverApi');
        $property->setAccessible(TRUE);
        $property->setValue(NULL, $server_api);
        $this->config('automatic_updates.settings')
          ->set('cron_port', $alternate_port ? 2501 : 0)
          ->save();
      }
    );
    // If errors were expected, cron should not have run.
    $this->container->get('cron')->run();
    if ($expected_results) {
      $this->assertUpdateStagedTimes(1);
      $error = new StageValidationException($expected_results);
      $this->assertTrue($logger->hasRecord($error->getMessage(), (string) RfcLogLevel::ERROR));
    }
    else {
      $this->assertFalse($logger->hasRecords((string) RfcLogLevel::ERROR));
    }
  }

  /**
   * Tests server validation for unattended updates with Help enabled.
   *
   * @param bool $alternate_port
   *   Whether or not an alternate port should be set.
   * @param string $server_api
   *   The value of the PHP_SAPI constant, as known to the validator.
   * @param string $cron_mode
   *   The cron mode to test with. Can contain be of
   *   \Drupal\automatic_updates\CronUpdater::DISABLED,
   *   \Drupal\automatic_updates\CronUpdater::SECURITY, or
   *   \Drupal\automatic_updates\CronUpdater::ALL.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerCronServerValidation
   */
  public function testHelpLink(bool $alternate_port, string $server_api, string $cron_mode, array $expected_results): void {
    $this->enableModules(['help']);

    $url = Url::fromRoute('help.page')
      ->setRouteParameter('name', 'automatic_updates')
      ->setOption('fragment', 'cron-alternate-port')
      ->toString();

    foreach ($expected_results as $i => $result) {
      $messages = [];
      foreach ($result->getMessages() as $message) {
        $messages[] = "$message See <a href=\"$url\">the Automatic Updates help page</a> for more information on how to resolve this.";
      }
      $expected_results[$i] = ValidationResult::createError($messages);
    }
    $this->testCronServerValidationDuringPreApply($alternate_port, $server_api, $cron_mode, $expected_results);
  }

}

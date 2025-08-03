<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdater;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use Drupal\Tests\package_manager\Traits\PackageManagerBypassTestTrait;
use ColinODell\PsrTestLogger\TestLogger;

/**
 * @covers \Drupal\automatic_updates\Validator\XdebugValidator
 * @group automatic_updates
 * @internal
 */
class XdebugValidatorTest extends AutomaticUpdatesKernelTestBase {

  use PackageManagerBypassTestTrait;
  use StringTranslationTrait;
  use StatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Tests warnings and/or errors if Xdebug is enabled.
   */
  public function testXdebugValidation(): void {
    $this->simulateXdebugEnabled();
    $message = $this->t('Xdebug is enabled, which may have a negative performance impact on Package Manager and any modules that use it.');
    $error = $this->t("Xdebug is enabled, currently Cron Updates are not allowed while it is enabled. If Xdebug is not disabled you will not receive security and other updates during cron.");

    $config = $this->config('automatic_updates.settings');
    // If cron updates are disabled, the status check message should only be
    // a warning.
    $config->set('cron', CronUpdater::DISABLED)->save();

    // Tests that other projects which depend on Package manager also get the
    // warning.
    $stage = $this->createStage();
    $this->assertUpdateStagedTimes(0);
    $stage->create();
    $stage->require(['drupal/random']);
    $this->assertUpdateStagedTimes(1);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $result = $this->runStatusCheck($stage, $event_dispatcher, TRUE);
    $this->assertSame($message->getUntranslatedString(), $result[0]->getMessages()[0]->getUntranslatedString());
    $stage->destroy(TRUE);

    $result = ValidationResult::createWarning([$message]);
    $this->assertCheckerResultsFromManager([$result], TRUE);

    // The parent class' setUp() method simulates an available security update,
    // so ensure that the cron updater will try to update to it.
    $config->set('cron', CronUpdater::SECURITY)->save();

    // If cron updates are enabled the status check message should be an
    // error.
    $result = ValidationResult::createError([$error]);
    $this->assertCheckerResultsFromManager([$result], TRUE);

    // Trying to do the update during cron should fail with an error.
    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);

    $this->container->get('cron')->run();
    // Assert there was not another update staged during cron.
    $this->assertUpdateStagedTimes(1);
    $this->assertTrue($logger->hasRecordThatMatches("/$error/", (string) RfcLogLevel::ERROR));
  }

  /**
   * Tests warnings and/or errors if Xdebug is enabled during pre-apply.
   */
  public function testXdebugValidationDuringPreApply(): void {
    $listener = function (): void {
      $this->simulateXdebugEnabled();
    };
    $this->addEventTestListener($listener);
    $message = "Xdebug is enabled, currently Cron Updates are not allowed while it is enabled. If Xdebug is not disabled you will not receive security and other updates during cron.";

    // The parent class' setUp() method simulates an available security
    // update, so ensure that the cron updater will try to update to it.
    $this->config('automatic_updates.settings')->set('cron', CronUpdater::SECURITY)->save();

    // Trying to do the update during cron should fail with an error.
    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);
    $this->container->get('cron')->run();
    $this->assertUpdateStagedTimes(1);
    $this->assertTrue($logger->hasRecordThatMatches("/$message/", (string) RfcLogLevel::ERROR));
  }

  /**
   * Simulating that xdebug is enabled.
   */
  private function simulateXdebugEnabled(): void {
    if (!function_exists('xdebug_break')) {
      // @codingStandardsIgnoreLine
      eval('function xdebug_break() {}');
      // @see \Drupal\package_manager\Validator\XdebugValidator::checkForXdebug()
      // @codingStandardsIgnoreLine
      eval('function xdebug_break_TESTED() {}');
    }
  }

}

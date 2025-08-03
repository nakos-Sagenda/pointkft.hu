<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdater;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\automatic_updates_test\Datetime\TestTime;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\system\SystemManager;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use Drupal\Tests\automatic_updates\Traits\EmailNotificationsTestTrait;

/**
 * Tests status check failure notification emails during cron runs.
 *
 * @group automatic_updates
 * @covers \Drupal\automatic_updates\StatusCheckMailer
 * @internal
 */
class StatusCheckFailureEmailTest extends AutomaticUpdatesKernelTestBase {

  use EmailNotificationsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
    'package_manager_test_validation',
    'user',
  ];

  /**
   * The number of times cron has been run.
   *
   * @var int
   */
  private $cronRunCount = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Simulate that we're already fully up to date.
    $this->setCoreVersion('9.8.1');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);

    $this->installConfig('automatic_updates');
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $this->config('automatic_updates.settings')->set('cron', CronUpdater::SECURITY)->save();
    $this->setUpEmailRecipients();

    // Allow stored available update data to live for a very, very long time.
    // By default, the data expires after one day, but this test runs cron many
    // times, with a simulated two hour interval between each run (see
    // ::runCron()). Without this long grace period, all the cron runs in this
    // test would need to run on the same "day", to prevent certain validators
    // from breaking this test due to available update data being irretrievable.
    $this->config('update.settings')
      ->set('check.interval_days', 30)
      ->save();
  }

  /**
   * Runs cron, simulating a two-hour interval since the previous run.
   *
   * We need to simulate that at least an hour has passed since the previous
   * run, so that our cron hook will run status checks again.
   *
   * @see automatic_updates_cron()
   */
  private function runCron(): void {
    $offset = $this->cronRunCount * 2;
    $this->cronRunCount++;
    TestTime::setFakeTimeByOffset("+$offset hours");
    $this->container->get('cron')->run();
  }

  /**
   * Asserts that a certain number of failure notifications has been sent.
   *
   * @param int $expected_count
   *   The expected number of failure notifications that should have been sent.
   */
  private function assertSentMessagesCount(int $expected_count): void {
    $sent_messages = $this->getMails([
      'id' => 'automatic_updates_status_check_failed',
    ]);
    $this->assertCount($expected_count, $sent_messages);
  }

  /**
   * Tests that status check failures will trigger e-mails in some situations.
   */
  public function testFailureNotifications(): void {
    // No messages should have been sent yet.
    $this->assertSentMessagesCount(0);

    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runCron();

    $url = Url::fromRoute('system.status')
      ->setAbsolute()
      ->toString();

    $expected_body = <<<END
Your site has failed some readiness checks for automatic updates and may not be able to receive automatic updates until further action is taken. Please visit $url for more information.
END;
    $this->assertMessagesSent('Automatic updates readiness checks failed', $expected_body);

    // Running cron again should not trigger another e-mail (i.e., each
    // recipient has only been e-mailed once) since the results are unchanged.
    $recipient_count = count($this->emailRecipients);
    $this->assertGreaterThan(0, $recipient_count);
    $sent_messages_count = $recipient_count;
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If a different error is flagged, they should be e-mailed again.
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag the same error, but a new warning, they should not be e-mailed
    // again because we ignore warnings by default, and they've already been
    // e-mailed about this error.
    $results = [
      $error,
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If only a warning is flagged, they should not be e-mailed again because
    // we ignore warnings by default.
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we stop ignoring warnings, they should be e-mailed again because we
    // clear the stored results if the relevant configuration is changed.
    $config = $this->config('automatic_updates.settings');
    $config->set('status_check_mail', StatusCheckMailer::ALL)->save();
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag a different warning, they should be e-mailed again.
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag multiple warnings, they should be e-mailed again because the
    // number of results has changed, even if the severity hasn't.
    $warnings = [
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($warnings, StatusCheckEvent::class);
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag an error and a warning, they should be e-mailed again because
    // the severity has changed, even if the number of results hasn't.
    $results = [
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
      $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
    ];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we change the order of the results, they should not be e-mailed again
    // because we are handling the possibility of the results being in a
    // different order.
    $results = array_reverse($results);
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we disable notifications entirely, they should not be e-mailed even
    // if a different error is flagged.
    $config->set('status_check_mail', StatusCheckMailer::DISABLED)->save();
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we re-enable notifications and go back to ignoring warnings, they
    // should not be e-mailed if a new warning is flagged.
    $config->set('status_check_mail', StatusCheckMailer::ERRORS_ONLY)->save();
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we disable unattended updates entirely and flag a new error, they
    // should not be e-mailed.
    $config->set('cron', CronUpdater::DISABLED)->save();
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runCron();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we re-enable unattended updates, they should be emailed again, even if
    // the results haven't changed.
    $config->set('cron', CronUpdater::ALL)->save();
    $this->runCron();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);
  }

}

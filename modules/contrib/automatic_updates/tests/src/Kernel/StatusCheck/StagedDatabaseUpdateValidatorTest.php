<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use ColinODell\PsrTestLogger\TestLogger;

/**
 * @covers \Drupal\automatic_updates\Validator\StagedDatabaseUpdateValidator
 * @group automatic_updates
 * @internal
 */
class StagedDatabaseUpdateValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * The extensions that will be used in this test.
   *
   * System and Stark are installed, so they are used to test what happens when
   * database updates are detected in installed extensions. Views and Olivero
   * are not installed by this test, so they are used to test what happens when
   * uninstalled extensions have database updates.
   *
   * @var string[]
   *
   * @see ::setUp()
   */
  private $extensions = [
    'system' => 'core/modules/system',
    'views' => 'core/modules/views',
    'stark' => 'core/themes/stark',
    'olivero' => 'core/themes/olivero',
  ];

  /**
   * The test logger to collected messages logged by the cron updater.
   *
   * @var \Psr\Log\Test\TestLogger
   */
  private $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['stark']);
    $this->assertFalse($this->container->get('module_handler')->moduleExists('views'));
    $this->assertFalse($this->container->get('theme_handler')->themeExists('olivero'));

    // Ensure that all the extensions we're testing with have database update
    // files in the active directory.
    $active_dir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();

    foreach ($this->extensions as $extension_name => $extension_path) {
      $extension_path = $active_dir . '/' . $extension_path;
      mkdir($extension_path, 0777, TRUE);

      foreach ($this->providerSuffixes() as [$suffix]) {
        touch("$extension_path/$extension_name.$suffix");
      }
    }

    $this->logger = new TestLogger();
    $this->container->get('logger.channel.automatic_updates')
      ->addLogger($this->logger);
  }

  /**
   * Data provider for several test methods.
   *
   * @return \string[][]
   *   The test cases.
   */
  public function providerSuffixes(): array {
    return [
      'hook_update_N' => ['install'],
      'hook_post_update_NAME' => ['post_update.php'],
    ];
  }

  /**
   * Tests that no errors are raised if the stage has no DB updates.
   */
  public function testNoUpdates(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $this->container->get('cron')->run();
    $this->assertFalse($this->logger->hasRecords((string) RfcLogLevel::ERROR));
  }

  /**
   * Tests that an error is raised if DB update files are removed in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileDeleted(string $suffix): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $listener = function (PreApplyEvent $event) use ($suffix): void {
      $stage_dir = $event->getStage()->getStageDirectory();
      foreach ($this->extensions as $name => $path) {
        unlink("$stage_dir/$path/$name.$suffix");
      }
    };
    $this->addEventTestListener($listener);

    $this->container->get('cron')->run();
    $expected_message = "The update cannot proceed because possible database updates have been detected in the following extensions.\nSystem\nStark\n";
    $this->assertTrue($this->logger->hasRecord($expected_message, (string) RfcLogLevel::ERROR));
  }

  /**
   * Tests that an error is raised if DB update files are changed in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileChanged(string $suffix): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $listener = function (PreApplyEvent $event) use ($suffix): void {
      $stage_dir = $event->getStage()->getStageDirectory();
      foreach ($this->extensions as $name => $path) {
        file_put_contents("$stage_dir/$path/$name.$suffix", $this->randomString());
      }
    };
    $this->addEventTestListener($listener);

    $this->container->get('cron')->run();
    $expected_message = "The update cannot proceed because possible database updates have been detected in the following extensions.\nSystem\nStark\n";
    $this->assertTrue($this->logger->hasRecord($expected_message, (string) RfcLogLevel::ERROR));
  }

  /**
   * Tests that an error is raised if DB update files are added in the stage.
   *
   * @param string $suffix
   *   The update file suffix to test (one of `install` or `post_update.php`).
   *
   * @dataProvider providerSuffixes
   */
  public function testFileAdded(string $suffix): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $listener = function () use ($suffix): void {
      $active_dir = $this->container->get('package_manager.path_locator')
        ->getProjectRoot();

      foreach ($this->extensions as $name => $path) {
        unlink("$active_dir/$path/$name.$suffix");
      }
    };
    $this->addEventTestListener($listener);

    $this->container->get('cron')->run();
    $expected_message = "The update cannot proceed because possible database updates have been detected in the following extensions.\nSystem\nStark\n";
    $this->assertTrue($this->logger->hasRecord($expected_message, (string) RfcLogLevel::ERROR));
  }

}

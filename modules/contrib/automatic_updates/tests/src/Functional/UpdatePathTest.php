<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\CronUpdater;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;

/**
 * @group automatic_updates
 * @internal
 */
class UpdatePathTest extends UpdatePathTestBase {

  use AssertPreconditionsTrait;

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    // phpcs on 9.5 expects one thing, on 10.0 another. 🤷
    // @see https://www.drupal.org/project/automatic_updates/issues/3314137#comment-14771510
    // phpcs:disable
    [$version] = explode('.', \Drupal::VERSION, 2);
    $this->databaseDumpFiles = [
      $version == 9
        ? $this->getDrupalRoot() . '/core/modules/system/tests/fixtures/update/drupal-9.3.0.filled.standard.php.gz'
        : $this->getDrupalRoot() . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz',
      __DIR__ . '/../../fixtures/automatic_updates-installed.php',
    ];
  }

  /**
   * Tests the update path for Automatic Updates.
   */
  public function testUpdatePath(): void {
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $key_value */
    $key_value = $this->container->get('keyvalue.expirable')
      ->get('automatic_updates');

    $map = [
      'readiness_validation_last_run' => 'status_check_last_run',
      'readiness_check_timestamp' => 'status_check_timestamp',
    ];
    $expected_values = [];
    foreach ($map as $old_key => $new_key) {
      $this->assertFalse($key_value->has($new_key));

      $value = $key_value->get($old_key);
      $this->assertNotEmpty($value);
      // Allow testing that the values post-update are indeed the values
      // pre-update and not recomputed ones.
      $expected_values[$new_key] = $value;
      // Ensure the stored value will still be retrievable.
      $key_value->setWithExpire($old_key, $value, 3600);
    }
    $this->assertEmpty($this->config('automatic_updates.settings')->get('status_check_mail'));

    $this->assertSame(CronUpdater::SECURITY, $this->config('automatic_updates.settings')->get('cron'));

    $this->assertSame(NULL, $this->config('package_manager.settings')->get('additional_trusted_composer_plugins'));

    $this->runUpdates();

    $this->assertSame(CronUpdater::DISABLED, $this->config('automatic_updates.settings')->get('cron'));

    // TRICKY: we do expect `readiness_validation_last_run` to have been renamed
    // to `status_check_last_run`, but then
    // automatic_updates_post_update_create_status_check_mail_config() should
    // cause that to be erased.
    // @see automatic_updates_post_update_create_status_check_mail_config()
    // @see \Drupal\automatic_updates\EventSubscriber\ConfigSubscriber::onConfigSave()
    unset($expected_values['status_check_last_run']);
    $this->assertSame($expected_values, $key_value->getMultiple(array_values($map)));
    $this->assertSame(StatusCheckMailer::ERRORS_ONLY, $this->config('automatic_updates.settings')->get('status_check_mail'));

    $this->assertSame([], $this->config('package_manager.settings')->get('additional_trusted_composer_plugins'));

    // Ensure that the router was rebuilt and routes have the expected changes.
    $routes = $this->container->get('router')->getRouteCollection();
    $routes = array_map([$routes, 'get'], [
      'system.batch_page.html',
      'system.status',
      'system.theme_install',
      'update.confirmation_page',
      'update.module_install',
      'update.module_update',
      'update.report_install',
      'update.report_update',
      'update.status',
      'update.theme_update',
      'automatic_updates.status_check',
    ]);
    foreach ($routes as $route) {
      $this->assertNotEmpty($route);
      $this->assertSame('skip', $route->getOption('_automatic_updates_status_messages'));
      $this->assertFalse($route->hasOption('_automatic_updates_readiness_messages'));
    }
  }

}

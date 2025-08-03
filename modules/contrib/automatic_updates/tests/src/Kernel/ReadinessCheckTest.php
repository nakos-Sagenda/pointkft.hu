<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Kernel;

use Drupal\automatic_updates\Event\ReadinessCheckEvent;
use Drupal\package_manager\StatusCheckTrait;

/**
 * Tests that running readiness checks raises deprecation notices.
 *
 * @group legacy
 * @internal
 */
class ReadinessCheckTest extends AutomaticUpdatesKernelTestBase {

  use StatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->addEventTestListener(function () {}, ReadinessCheckEvent::class);
  }

  /**
   * Tests running readiness check via StatusCheckTrait.
   */
  public function testStatusCheckTrait(): void {
    $this->expectDeprecation(ReadinessCheckEvent::class . ' is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. Use \Drupal\package_manager\Event\StatusCheckEvent instead. See https://www.drupal.org/node/3316086.');
    $this->runStatusCheck($this->createStage(), $this->container->get('event_dispatcher'), TRUE);
  }

  /**
   * Tests running readiness checks using the readiness validation manager.
   */
  public function testReadinessValidationManager(): void {
    $this->expectDeprecation('The "automatic_updates.readiness_validation_manager" service is deprecated in automatic_updates:8.x-2.5 and is removed from automatic_updates:3.0.0. Use the automatic_updates.status_checker service instead. See https://www.drupal.org/node/3316086.');
    $this->expectDeprecation(ReadinessCheckEvent::class . ' is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. Use \Drupal\package_manager\Event\StatusCheckEvent instead. See https://www.drupal.org/node/3316086.');
    $this->container->get('automatic_updates.readiness_validation_manager')
      ->run();
  }

}

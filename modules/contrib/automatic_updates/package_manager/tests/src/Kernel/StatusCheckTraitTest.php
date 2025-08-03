<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\ValidationResult;

/**
 * @covers \Drupal\package_manager\StatusCheckTrait
 * @group package_manager
 * @internal
 */
class StatusCheckTraitTest extends PackageManagerKernelTestBase {

  use StatusCheckTrait;

  /**
   * Tests that StatusCheckTrait will collect ignored paths.
   */
  public function testIgnoredPathsCollected(): void {
    $this->addEventTestListener(function (CollectIgnoredPathsEvent $event): void {
      $event->add(['/junk/drawer']);
    }, CollectIgnoredPathsEvent::class);

    $status_check_called = FALSE;
    $this->addEventTestListener(function (StatusCheckEvent $event) use (&$status_check_called): void {
      $this->assertContains('/junk/drawer', $event->getExcludedPaths());
      $status_check_called = TRUE;
    }, StatusCheckEvent::class);
    $this->runStatusCheck($this->createStage(), $this->container->get('event_dispatcher'));
    $this->assertTrue($status_check_called);
  }

  /**
   * Tests StatusCheckTrait returns an error when unable to get ignored paths.
   */
  public function testErrorIgnoredPathsCollected(): void {
    $composer_json_path = $this->container->get('package_manager.path_locator')->getProjectRoot() . '/composer.json';
    // Delete composer.json, so we won't be able to get excluded paths.
    unlink($composer_json_path);
    $this->addEventTestListener(function (CollectIgnoredPathsEvent $event): void {
      // Try to get composer.
      $event->getStage()->getActiveComposer();
    }, CollectIgnoredPathsEvent::class);
    $results = $this->runStatusCheck($this->createStage(), $this->container->get('event_dispatcher'));
    $expected_results = [
      ValidationResult::createErrorFromThrowable(
        new \Exception("Composer could not find the config file: $composer_json_path\n"),
        t("Unable to collect ignored paths, therefore can't perform status checks."),
      ),
    ];
    $this->assertValidationResultsEqual($expected_results, $results);
  }

}

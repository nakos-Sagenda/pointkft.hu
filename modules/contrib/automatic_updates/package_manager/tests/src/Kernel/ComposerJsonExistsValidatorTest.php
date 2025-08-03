<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\StageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;

/**
 * @covers \Drupal\package_manager\Validator\ComposerJsonExistsValidator
 * @group package_manager
 */
class ComposerJsonExistsValidatorTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['package_manager_test_validation'];

  /**
   * Tests validation when the active composer.json is not present.
   */
  public function testComposerRequirement(): void {
    $listener = function (StageEvent $event): void {
      unlink($this->container->get('package_manager.path_locator')
        ->getProjectRoot() . '/composer.json');
    };
    $this->addEventTestListener($listener, PreCreateEvent::class, 1000);
    $result = ValidationResult::createError([t(
      'No composer.json file can be found at <PROJECT_ROOT>'),
    ]);
    foreach ([PreCreateEvent::class, StatusCheckEvent::class] as $event_class) {
      $this->assertEventPropagationStopped($event_class, [$this->container->get('package_manager.validator.composer_json_exists'), 'validateComposerJson']);
    }
    $this->assertResults([$result], PreCreateEvent::class);
    $result = ValidationResult::createError(
      [
        t("Composer could not find the config file: <PROJECT_ROOT>/composer.json\n"),
      ],
      t("Unable to collect ignored paths, therefore can't perform status checks.")
    );

    $this->assertStatusCheckResults([$result]);
  }

  /**
   * Tests that active composer.json is not present during pre-apply.
   */
  public function testComposerRequirementDuringPreApply(): void {
    $result = ValidationResult::createError([t(
      'No composer.json file can be found at <PROJECT_ROOT>'),
    ]);
    $this->addEventTestListener(function (): void {
      unlink($this->container->get('package_manager.path_locator')
        ->getProjectRoot() . '/composer.json');
    });
    $this->assertEventPropagationStopped(PreApplyEvent::class, [$this->container->get('package_manager.validator.composer_json_exists'), 'validateComposerJson']);
    $this->assertResults([$result], PreApplyEvent::class);
  }

}

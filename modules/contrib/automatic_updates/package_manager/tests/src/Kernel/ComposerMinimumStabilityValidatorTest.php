<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\ValidationResult;

/**
 * @covers \Drupal\package_manager\Validator\ComposerMinimumStabilityValidator
 * @group package_manager
 * @internal
 */
class ComposerMinimumStabilityValidatorTest extends PackageManagerKernelTestBase {

  /**
   * Tests error if requested version is less stable than the minimum: stable.
   */
  public function testPreRequireEvent(): void {
    $stage = $this->createStage();
    $stage->create();
    $result = ValidationResult::createError([
      t("<code>drupal/core</code>'s requested version 9.8.1-beta1 is less stable (beta) than the minimum stability (stable) required in <PROJECT_ROOT>/composer.json."),
    ]);
    try {
      $stage->require(['drupal/core:9.8.1-beta1']);
      $this->fail('Able to require a package even though it did not meet minimum stability.');
    }
    catch (StageValidationException $exception) {
      $this->assertValidationResultsEqual([$result], $exception->getResults());
    }
    $stage->destroy();

    // Specifying a stability flag bypasses this check.
    $stage1 = $this->createStage();
    $stage1->create();
    $stage1->require(['drupal/core:9.8.1-beta1@dev']);
  }

}

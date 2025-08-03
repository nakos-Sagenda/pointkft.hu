<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Exception\ApplyFailedException;

/**
 * @coversDefaultClass \Drupal\package_manager\FailureMarker
 * @group package_manager
 * @internal
 */
class FailureMarkerTest extends PackageManagerKernelTestBase {
  use StringTranslationTrait;

  /**
   * @covers ::assertNotExists
   */
  public function testExceptionIfExists(): void {
    $failure_marker = $this->container->get('package_manager.failure_marker');
    $failure_marker->write($this->createStage(), $this->t('Disastrous catastrophe!'));

    $this->expectException(ApplyFailedException::class);
    $this->expectExceptionMessage('Disastrous catastrophe!');
    $failure_marker->assertNotExists();
  }

  /**
   * Tests that an exception is thrown if the marker file contains invalid JSON.
   *
   * @covers ::assertNotExists
   */
  public function testExceptionForInvalidJson(): void {
    $failure_marker = $this->container->get('package_manager.failure_marker');
    // Write the failure marker with invalid JSON.
    file_put_contents($failure_marker->getPath(), '{}}');

    $this->expectException(ApplyFailedException::class);
    $this->expectExceptionMessage('Failure marker file exists but cannot be decoded.');
    $failure_marker->assertNotExists();
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Unit;

use Drupal\package_manager\ProcessFactory;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\package_manager\ProcessFactory
 * @group automatic_updates
 * @internal
 */
class ProcessFactoryTest extends UnitTestCase {

  /**
   * Tests that the process factory prepends the PHP directory to PATH.
   */
  public function testPhpDirectoryPrependedToPath(): void {
    $factory = new ProcessFactory(
      $this->prophesize('\Drupal\Core\File\FileSystemInterface')->reveal(),
      $this->getConfigFactoryStub()
    );

    // Ensure that the directory of the PHP interpreter can be found.
    $reflector = new \ReflectionObject($factory);
    $method = $reflector->getMethod('getPhpDirectory');
    $method->setAccessible(TRUE);
    $php_dir = $method->invoke(NULL);
    $this->assertNotEmpty($php_dir);

    // The process factory should always put the PHP interpreter's directory
    // at the beginning of the PATH environment variable.
    $env = $factory->create(['whoami'])->getEnv();
    $this->assertStringStartsWith("$php_dir:", $env['PATH']);
  }

}

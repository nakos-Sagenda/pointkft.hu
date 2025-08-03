<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use PhpTuf\ComposerStager\Domain\Exception\RuntimeException;

/**
 * @coversDefaultClass \Drupal\package_manager\ComposerInspector
 *
 * @group package_manager
 */
class ComposerInspectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update', 'package_manager'];

  /**
   * @covers ::getConfig
   */
  public function testConfig(): void {
    $dir = __DIR__ . '/../../fixtures/fake_site';
    $inspector = $this->container->get('package_manager.composer_inspector');
    $this->assertSame(1, Json::decode($inspector->getConfig('secure-http', $dir)));

    $this->assertSame([
      'boo' => 'boo boo',
      "foo" => ["dev" => "2.x-dev"],
      "foo-bar" => TRUE,
      "boo-far" => [
        "foo" => 1.23,
        "bar" => 134,
        "foo-bar" => NULL,
      ],
      'baz' => NULL,
    ], Json::decode($inspector->getConfig('extra', $dir)));

    $this->expectException(RuntimeException::class);
    $inspector->getConfig('non-existent-config', $dir);
  }

  /**
   * @covers ::getVersion
   */
  public function testGetVersion() {
    $dir = __DIR__ . '/../../fixtures/fake_site';
    $inspector = $this->container->get('package_manager.composer_inspector');
    $version = $inspector->getVersion($dir);
    // We can assert an exact version of Composer, but we can assert that the
    // number is in the expected 'MAJOR.MINOR.PATCH' format.
    $parts = explode('.', $version);
    $this->assertCount(3, $parts);
    $this->assertCount(3, array_filter($parts, 'is_numeric'));
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->getDefinition('package_manager.composer_inspector')->setPublic(TRUE);
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Stage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;

/**
 * Tests that Package Manager's requirements check for the failure marker.
 *
 * @group package_manager
 * @internal
 */
class FailureMarkerRequirementTest extends BrowserTestBase {
  use StringTranslationTrait;

  use AssertPreconditionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager',
    'package_manager_bypass',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that error is shown if failure marker already exists.
   */
  public function testFailureMarkerExists() {
    $account = $this->drupalCreateUser([
      'administer site configuration',
    ]);
    $this->drupalLogin($account);

    $fake_project_root = $this->root . DIRECTORY_SEPARATOR . $this->publicFilesDirectory;
    $this->container->get('package_manager.path_locator')
      ->setPaths($fake_project_root, NULL, NULL, NULL);

    $failure_marker = $this->container->get('package_manager.failure_marker');
    $message = $this->t('Package Manager is here to wreck your day.');
    $failure_marker->write($this->createMock(Stage::class), $message);
    $path = $failure_marker->getPath();
    $this->assertFileExists($path);
    $this->assertStringStartsWith($fake_project_root, $path);

    $this->drupalGet('/admin/reports/status');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Failed update detected');
    $assert_session->pageTextContains($message);
  }

}

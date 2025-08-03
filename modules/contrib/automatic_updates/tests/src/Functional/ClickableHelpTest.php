<?php

declare(strict_types = 1);

namespace Drupal\Tests\automatic_updates\Functional;

/**
 * Tests package manager help link is clickable.
 *
 * @group automatic_updates
 * @internal
 */
class ClickableHelpTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'help',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    unset($this->disableValidators[array_search('package_manager.validator.composer_executable', $this->disableValidators)]);
    parent::setUp();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $this->checkerRunnerUser = $this->createUser([
      'administer site configuration',
    ]);
  }

  /**
   * Tests if composer executable is not present then the help link clickable.
   */
  public function testHelpLinkClickable(): void {
    $this->drupalLogin($this->checkerRunnerUser);
    $this->config('package_manager.settings')
      ->set('executables.composer', '/not/matching/path/to/composer')
      ->save();
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->linkByHrefExists('/admin/help/package_manager#package-manager-faq-composer-not-found');
  }

}

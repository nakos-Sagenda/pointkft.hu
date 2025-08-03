<?php

namespace Drupal\Tests\entity_update\Functional;

error_reporting(0);
use Drupal\Tests\BrowserTestBase;

/**
 * Test uninstall functionality of Site Version module.
 *
 * @group field_gallery
 */
class InstallUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_update'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer modules',
    ];

    // User to set up entity_update.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Test uninstall the module without mishap.
   */
  public function testInstallUninstallInt() {

    // Test if site opens with no errors.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $installer */
    $installer = $this->container->get('module_installer');
    $this->assertTrue($installer->uninstall(['entity_update']));

    // Install test module.
    \Drupal::service('module_installer')->install(['entity_update_tests']);
    $this->assertTrue($installer->uninstall(['entity_update']));

    // Re-test if site opens with no errors.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test that we can uninstall by interface.
   */
  public function testInstallUninstallWeb() {
    $assert = $this->assertSession();

    // Test if site opens with no errors.
    $this->drupalGet('');
    $assert->statusCodeEquals(200);

    // Uninstall the module field_gallery.
    $edit = [];
    $edit['uninstall[entity_update]'] = TRUE;
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm($edit, 'Uninstall');
    $assert->pageTextContains('Entity Update');
    $this->submitForm(NULL, 'Uninstall');
    $assert->pageTextContains('The selected modules have been uninstalled.');

    // Re test if site opens with no errors.
    $this->drupalGet('');
    $assert->statusCodeEquals(200);
  }

}

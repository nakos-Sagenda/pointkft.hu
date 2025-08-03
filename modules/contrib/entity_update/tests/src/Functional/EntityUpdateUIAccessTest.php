<?php

namespace Drupal\Tests\entity_update\Functional;

error_reporting(0);
use Drupal\Tests\BrowserTestBase;

/**
 * Test Entity Update UI Access.
 *
 * @group Entity Update
 */
class EntityUpdateUIAccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_update', 'entity_update_tests'];


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
      'administer software updates',
    ];

    // A simple user without any specific permissions.
    $this->user = $this->drupalCreateUser([]);
    // User to set up entity_update.
    $this->admin_user = $this->drupalCreateUser($permissions);
  }

  /**
   * Tests Pages for Anonymous users.
   */
  public function testAnonymousAccess() {

    // Run tests for Anonymous users.
    $this->runPageAccess(403, 'testAnonymousAccess');
  }

  /**
   * Tests Pages for Simple users.
   */
  public function testSimpleUserAccess() {

    // Simple user login.
    $this->drupalLogin($this->user);

    // Run tests.
    $this->runPageAccess(403, 'testSimpleUserAccess');
  }

  /**
   * Tests Pages for admin user.
   */
  public function testAdminsAccess() {
    // Admin user login.
    $this->drupalLogin($this->admin_user);

    // Run tests.
    $this->runPageAccess(200, 'testAdminsAccess');
  }

  /**
   * Run page tests.
   */
  private function runPageAccess($code = NULL, $method = '') {

    $assert = $this->assertSession();

    // Check home page.
    $this->drupalGet('');
    $assert->statusCodeEquals(200);

    // Return if NULL.
    if (!$code) {
      return;
    }

    $this->drupalGet('admin/config/development/entity-update');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/tests');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/exec');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/types');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/list');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/list/user/1');
    $assert->statusCodeEquals($code);

    $this->drupalGet('admin/config/development/entity-update/list/user/1/2');
    $assert->statusCodeEquals($code);
  }

}

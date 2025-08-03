<?php

namespace Drupal\Tests\entity_update\Functional;

error_reporting(0);
use Drupal\entity_update\EntityUpdate;
use Drupal\entity_update_tests\Entity\EntityUpdateTestsContentEntity;
use Drupal\entity_update_tests\EntityUpdateTestHelper;
use Drupal\Tests\BrowserTestBase;

/**
 * Test Entity Update UI Functions.
 *
 * @group Entity Update
 */
class EntityUpdateUIFunctionsTest extends BrowserTestBase {

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

    $permissions = ['administer software updates'];
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);

    // Disable drush print.
    EntityUpdatePrint::setEchoPrintEnable(FALSE);
  }

  /**
   * Run basic tests.
   */
  public function testEntityUpdateBasicWebExec() {

    $assert = $this->assertSession();

    // Initially, Disable the field 'name' => No need to update.
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldDisable('description');
    EntityUpdateTestHelper::fieldSetType('type', NULL);

    // Check no updates message.
    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('All Entities are up to date.');

    // Check no updates message.
    $edit = [];
    $path = 'admin/config/development/entity-update/exec/basic';
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Basic Update');
    $assert->pageTextContainsOnce('Nothing to update. All entities are up to date.');

    // One field to install.
    EntityUpdateTestHelper::fieldEnable('name');

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('The Name field needs to be installed.');

    $edit = [];
    $edit['confirm'] = FALSE;
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Basic Update');
    $assert->pageTextContainsOnce('If you want to execute, please check the checkbox.');

    $edit = [];
    $edit['confirm'] = TRUE;
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Basic Update');
    $assert->pageTextContainsOnce('Entity update SUCCESS');

    // One field to install, one field to uninstall.
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldEnable('description');

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('The Description field needs to be installed.');
    $assert->pageTextContainsOnce('The Name field needs to be uninstalled.');

    $edit = [];
    $edit['confirm'] = TRUE;
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Basic Update');
    $assert->pageTextContainsOnce('Entity update SUCCESS');

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('All Entities are up to date.');
  }

  /**
   * Run basic tests.
   */
  public function testEntityUpdateSelectedWebExec() {

    $assert = $this->assertSession();

    // Initially, Disable the field 'name' => No need to update.
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldDisable('description');
    EntityUpdateTestHelper::fieldSetType('type', NULL);

    // Check no updates message.
    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('All Entities are up to date.');

    // Check no updates message.
    $path = 'admin/config/development/entity-update/exec/type';
    $this->drupalGet($path);
    $assert->pageTextContainsOnce('Nothing to update');

    // One field to install.
    EntityUpdateTestHelper::fieldEnable('name');

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('The Name field needs to be installed.');

    $edit = [];
    $edit['confirm'] = FALSE;
    $edit['entity_type_id'] = 'entity_update_tests_cnt';
    $this->drupalGet($path);
    $assert->elementTextContains('css', '#edit-entity-type-id option[value=entity_update_tests_cnt]', 'entity_update_tests_cnt');
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Type Update');
    $assert->pageTextContainsOnce('If you want to execute, please check the checkbox.');

    $edit['confirm'] = TRUE;
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Type Update');
    $assert->pageTextContainsOnce('Entity update SUCCESS');

    // One field to install, one field to uninstall (Check error message).
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldEnable('description');

    $this->drupalGet('admin/config/development/entity-update/status');
    $assert->pageTextContainsOnce('The Description field needs to be installed.');
    $assert->pageTextContainsOnce('The Name field needs to be uninstalled.');

    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Type Update');
    $assert->pageTextContainsOnce('Multiple actions detected, cant update if contains data. Use basic method.');
  }

  /**
   * Test cleanup.
   */
  public function testEntityUpdateCleanupWebExec() {
    $assert = $this->assertSession();
    $path = 'admin/config/development/entity-update/exec/clean';

    $edit = [];
    $edit['confirm'] = FALSE;
    $this->drupalGet($path);
    $this->drupalGet($path);
    $this->submitForm($edit, 'Cleanup');
    $assert->pageTextContainsOnce('If you want to execute, please check the checkbox.');

    $edit['confirm'] = TRUE;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Cleanup');
    $assert->pageTextContainsOnce('Backups cleanup SUCCESS');
  }

  /**
   * Test rescue.
   */
  public function testEntityUpdateRescueWebExec() {

    $assert = $this->assertSession();
    $path = 'admin/config/development/entity-update/exec/rescue';

    $edit = [];
    $edit['confirm'] = FALSE;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Entity Rescue');
    $assert->pageTextContainsOnce('If you want to execute, please check the checkbox.');

    $edit['confirm'] = TRUE;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Run Entity Rescue');
    // Nothing to rescue.
    $assert->pageTextContainsOnce('Entity rescue FAIL');

    // Real rescue test.
    EntityUpdateTestHelper::fieldEnable('name');
    EntityUpdateTestHelper::fieldEnable('description');
    EntityUpdate::basicUpdate();
    $data = ['id' => 1, 'name' => 'name', 'description' => 'description'];
    $entity = EntityUpdateTestsContentEntity::create($data);
    $entity->save();
    EntityUpdateTestHelper::fieldDisable('name');
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema is updated (Uninstall + data).');
    $edit['confirm'] = TRUE;
    $this->drupalPostForm($path, $edit, 'Run Entity Rescue');
    // @todo Create a correct test.
  }

}

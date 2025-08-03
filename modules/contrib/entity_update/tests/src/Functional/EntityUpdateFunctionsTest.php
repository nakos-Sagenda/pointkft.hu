<?php

namespace Drupal\Tests\entity_update\Functional;

error_reporting(0);

use Drupal\entity_update\EntityUpdate;
use Drupal\entity_update\EntityUpdatePrint;
use Drupal\entity_update_tests\Entity\EntityUpdateTestsContentEntity;
use Drupal\entity_update_tests\EntityUpdateTestHelper;
use Drupal\Tests\BrowserTestBase;

/**
 * Test Entity Update functions.
 *
 * @group Entity Update
 */
class EntityUpdateFunctionsTest extends BrowserTestBase {

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

    // Detele the entity created by install.
    if ($entity = EntityUpdateTestsContentEntity::load(100)) {
      $entity->delete();
    }

    // Initially, Disable the field 'name' => No need to update.
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldDisable('description');
    EntityUpdateTestHelper::fieldSetType('type', NULL);

    // Disable drush print.
    EntityUpdatePrint::setEchoPrintEnable(FALSE);
  }

  /**
   * Entity update function : basic.
   */
  public function testEntityUpdateBasic() {

    // Disable the field by default => No need to update.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 0, 'Every entities are up to date.');

    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');

    // Get updates list.
    $list = EntityUpdate::getEntityTypesToUpdate();

    // Has one field to update.
    $this->assertTrue(count($list) === 1, 'Has only one entity type to update.');

    // Analyse Entity to update.
    $first_item = reset($list);
    $first_key = key($list);
    $this->assertEquals($first_key, 'entity_update_tests_cnt', 'The first key is "entity_update_tests_cnt".');
    $this->assertEquals(count($first_item), 1, 'The "entity_update_tests_cnt" has one change.');
    // Get first change.
    $entity_change_summ = reset($first_item);
    $temp = strip_tags($entity_change_summ);
    $this->assertEquals($temp, 'The Name field needs to be installed.', 'Summary text is correct.');
    // Make Update.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');
    // Get updates list and check.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 0, 'Every entities are up to date.');
    // Check fields list on database.
    $fields = ['fix', 'id', 'name'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');

    // Enable Type Field and set to 'string'.
    EntityUpdateTestHelper::fieldSetType('type', 'string');
    // Get updates list and check.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 1, 'Has one update.');
    // Make Update.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');
    // Get updates list and check.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 0, 'Every entities are up to date.');
    // Check fields list on database.
    $fields = ['fix', 'id', 'name', 'type'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');

    // Disable the field 'name'.
    EntityUpdateTestHelper::fieldDisable('name');
    // Has one field to update.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 1, 'Has one entity type to update.');
    // Make Update.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');
    // Has one field to update.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 0, 'Every entities are up to date.');

    // Enable Type Field and set to 'integer'.
    EntityUpdateTestHelper::fieldSetType('type', 'integer');
    // Has one field to update.
    $list = EntityUpdate::getEntityTypesToUpdate();
    $this->assertEquals(count($list), 1, 'Has one entity type to update.');
    // Make Update.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');
    // Check fields list on database.
    $fields = ['fix', 'id', 'type'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');
  }

  /**
   * Entity update function : basic --force.
   */
  public function testEntityUpdateBasicForce() {

    // Create an entity.
    $entity = EntityUpdateTestsContentEntity::create(['id' => 1]);
    $entity->save();
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');
    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');
    EntityUpdateTestHelper::fieldEnable('description');
    // Check and Make Update using --force.
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');

    // Disable field 'name'.
    EntityUpdateTestHelper::fieldDisable('name');

    // Update using --force.
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');

    // Update entity, add description.
    $entity = EntityUpdateTestsContentEntity::load(1);
    $entity->set('description', 'a description');
    $entity->save();
    EntityUpdateTestHelper::fieldDisable('description');
    // Update using --force (After D8.5 this will update).
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema has updated (Uninstall + data).');
  }

  /**
   * Entity update function : all.
   */
  public function testEntityUpdateAll() {

    // Create an entity.
    $entity = EntityUpdateTestsContentEntity::create(['id' => 1]);
    $entity->save();

    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');
    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');
    // Enable Type Field and set to 'string'.
    EntityUpdateTestHelper::fieldSetType('type', 'string');

    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 1, 'Has one entity type to update.');
    // Make Update.
    $res = EntityUpdate::safeUpdateMain();
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 0, 'Entity type updated.');

    // Check fields list on database.
    $fields = ['fix', 'id', 'name', 'type'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');

    // Load and update entity (after entity type update).
    $entity = EntityUpdateTestsContentEntity::load(1);
    $entity->set('name', 'value');
    $entity->save();

    // Disable the field 'name'.
    EntityUpdateTestHelper::fieldDisable('name');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 1, 'Has one entity type to update.');
    // Make Update.
    $res = EntityUpdate::safeUpdateMain();
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 0, 'Entity type updated.');

    // Check entity count.
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');

    // Check fields list on database.
    $fields = ['fix', 'id', 'type'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');
  }

  /**
   * Update a selected entity type.
   */
  public function testEntityUpdateSel() {

    // Create an entity.
    $entity = EntityUpdateTestsContentEntity::create(['id' => 1]);
    $entity->save();

    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');
    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');
    EntityUpdateTestHelper::fieldEnable('city');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 2, 'Has one entity type to update.');

    $entity_type = \Drupal::entityTypeManager()
      ->getStorage('entity_update_tests_cnt');
    // Make Update of the type.
    $res = EntityUpdate::safeUpdateMain($entity_type->getEntityType());
    $this->assertTrue($res, 'Entity schema has been updated (Field Add & Remove).');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 1, 'Entity type updated.');

    // Check fields list on database.
    $fields = ['fix', 'id', 'name'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');

    // Load and update entity (after entity type update).
    $entity = EntityUpdateTestsContentEntity::load(1);
    $entity->set('name', 'value');
    $entity->save();

    // Disable the field 'name'.
    EntityUpdateTestHelper::fieldDisable('name');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 2, 'Has one entity type to update.');
    // Make Update of the type.
    $res = EntityUpdate::safeUpdateMain($entity_type->getEntityType());
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');
    // Check update.
    $this->assertEquals(count(EntityUpdate::getEntityTypesToUpdate()), 1, 'Entity type updated.');

    // Check entity count.
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'After updates, Hase one entity.');

    // Check entity count.
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');

    // Check fields list on database.
    $fields = ['fix', 'id'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');
  }

  /**
   * Entity advanced update simulation.
   */
  public function testEntityUpdateAdvanced() {
    /* TODO : Review this test */
    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');
    EntityUpdateTestHelper::fieldEnable('description');
    // Update entity type.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');

    // Create an entity.
    $data = ['id' => 1, 'name' => 'name', 'description' => 'description'];
    $entity = EntityUpdateTestsContentEntity::create($data);
    $entity->save();
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');

    // Disable field 'name'.
    EntityUpdateTestHelper::fieldDisable('name');
    // Try update without --force.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue(!$res, 'Entity schema is NOT updated (Uninstall + data).');
    // Try update using --force (After D8.5 this will update).
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema is updated (Uninstall + data).');

    // Simulate advanced update. Re enable field.
    EntityUpdateTestHelper::fieldEnable('name');
    $res = EntityUpdate::basicUpdate(TRUE);
    $this->assertTrue($res, 'Entity schema is updated by advanced (Uninstall + data).');

    // Cleanup backup DB.
    $res = EntityUpdate::cleanupEntityBackup();
    // Copy to backup DB.
    $type = 'entity_update_tests_cnt';
    $res = EntityUpdate::entityUpdateDataBackupDel(EntityUpdate::getEntityTypesToUpdate($type), $type);
    $this->assertTrue($res, 'Entity data backup.');
    // Update codes (Disable field).
    EntityUpdateTestHelper::fieldDisable('name');
    // Update entity types.
    $res = EntityUpdate::basicUpdate();
    $this->assertTrue($res, 'Entity schema has updated (without data).');
    // Restore from backup DB.
    $res = EntityUpdate::entityUpdateDataRestore();
    $this->assertTrue($res, 'Entity data Restore.');
    // Count entities.
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');
    // Cleanup backup DB.
    $res = EntityUpdate::cleanupEntityBackup();
  }

  /**
   * Entity update function : clean.
   */
  public function testEntityUpdateClean() {
    // Create an entity.
    $entity = EntityUpdateTestsContentEntity::create(['id' => 1]);
    $entity->save();

    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');
    // Enable the field.
    EntityUpdateTestHelper::fieldEnable('name');
    // Make Update.
    $res = EntityUpdate::safeUpdateMain();
    $this->assertTrue($res, 'Entity schema has been updated (Field Add).');

    // Check fields list on database.
    $fields = ['fix', 'id', 'name'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');

    // Disable the field.
    EntityUpdateTestHelper::fieldDisable('name');
    // Make Update.
    $res = EntityUpdate::safeUpdateMain();
    $this->assertTrue($res, 'Entity schema has been updated (Field Remove).');
    // Cleanup function.
    $res = EntityUpdate::cleanupEntityBackup();
    $this->assertTrue($res, 'Table cleanup END.');

    // Check entity count.
    $ids = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();
    $this->assertEquals(count($ids), 1, 'Has one entity.');

    // Check fields list on database.
    $fields = ['fix', 'id'];
    $res = EntityUpdateTestHelper::checkFieldList('entity_update_tests_cnt', $fields);
    $this->assertTrue($res === TRUE, 'Entity schema database has correct fields [' . (print_r($res, TRUE)) . ']');
  }

}

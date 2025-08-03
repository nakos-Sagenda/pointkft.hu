<?php

namespace Drupal\Tests\entity_update\Functional;

error_reporting(0);

use Drupal\entity_update\EntityUpdate;
use Drupal\entity_update\EntityUpdatePrint;
use Drupal\entity_update_tests\Entity\EntityUpdateTestsContentEntity;
use Drupal\entity_update_tests\EntityUpdateTestHelper;
use Drupal\Tests\BrowserTestBase;

/**
 * Entity Update module, Update entities programmatically test.
 *
 * @group Entity Update
 */
class EntityUpdateProgUpTest extends BrowserTestBase {

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
    // Initialisation process.
    parent::setUp();

    // Set initial fields status (1 enebale, 1 disable).
    EntityUpdateTestHelper::fieldDisable('name');
    EntityUpdateTestHelper::fieldEnable('description');
    EntityUpdateTestHelper::fieldSetType('type', NULL);
    // Update.
    EntityUpdate::basicUpdate();
    // Create entities.
    EntityUpdateTestsContentEntity::create(['id' => 1])->save();

    // Disable drush print.
    EntityUpdatePrint::setEchoPrintEnable(FALSE);
  }

  /**
   * Entity update : add fields.
   *
   * Use this example if you add fields.
   * See the documentation for more details.
   */
  public function testProgUpdateAddFields() {

    // Enable the test field (Only for this test).
    EntityUpdateTestHelper::fieldEnable('name');

    // Entity checking process : Get current entities list.
    $ids_old = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();

    // Make Update Using full methode.
    if ($res = EntityUpdate::safeUpdateMain()) {
      // Your codes if update success.
    }
    else {
      // Your codes if update false.
    }
    $this->assertTrue($res, 'Entity schema has been updated successfully.');

    // Entity checking process : Compare with new list entities list.
    $ids_new = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();

    // Compare two lists.
    $result = array_diff($ids_old, $ids_new);
    if ($res = empty($result)) {
      // Your codes if update checking process success.
    }
    else {
      // Your codes if update false.
    }
    $this->assertTrue($res, 'Entity schema has been updated successfully.');
  }

  /**
   * Entity update : remove fields.
   *
   * Use this example if you remove fields.
   * See the documentation for more details.
   */
  public function testProgUpdateRemoveFields() {

    // Disable the test field (Only for this test).
    EntityUpdateTestHelper::fieldDisable('description');

    // Entity checking process : Get current entities list.
    $ids_old = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();

    // Make Update Using full methode.
    if ($res = EntityUpdate::safeUpdateMain()) {
      // Your codes if update success.
    }
    else {
      // Your codes if update false.
    }
    $this->assertTrue($res, 'Entity schema has been updated successfully.');

    // Entity checking process : Compare with new list entities list.
    $ids_new = \Drupal::entityQuery('entity_update_tests_cnt')->accessCheck(FALSE)->execute();

    // Compare two lists.
    $result = array_diff($ids_old, $ids_new);
    if ($res = empty($result)) {
      // Your codes if update checking process success.
      EntityUpdate::cleanupEntityBackup();
    }
    else {
      // Your codes if update false.
    }
    $this->assertTrue($res, 'Entity schema has been updated successfully.');
  }

}

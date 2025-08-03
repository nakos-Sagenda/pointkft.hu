<?php

namespace Drupal\entity_update_tests;

use Drupal\Core\Database\Database;

/**
 * EntityUpdateTest Helper functions.
 */
class EntityUpdateTestHelper {

  /**
   * Get Configuration Name.
   */
  public static function getConfigName() {
    return 'entity_update_tests.settings';
  }

  /**
   * Get Configuration Object.
   *
   * @param bool $editable
   *   Readonly or Editable.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  public static function getConfig($editable = FALSE) {
    if ($editable) {
      $config = \Drupal::configFactory()->getEditable(static::getConfigName());
    }
    else {
      $config = \Drupal::config(static::getConfigName());
    }
    return $config;
  }

  /**
   * Enable a configurable field.
   */
  public static function fieldEnable($name, $enable = TRUE) {
    $config = self::getConfig(TRUE);
    $config->set("fields." . $name, $enable);
    $config->save();
  }

  /**
   * Disable a configurable field.
   */
  public static function fieldDisable($name) {
    return self::fieldEnable($name, FALSE);
  }

  /**
   * Set Field type.
   */
  public static function fieldSetType($name, $type = 'string') {
    $config = self::getConfig(TRUE);
    $config->set("fields." . $name, $type);
    $config->save();
  }

  /**
   * Get a configurable field status or value.
   */
  public static function fieldStatus($name) {
    return self::getConfig()->get("fields." . $name);
  }

  /**
   * Check a database table fields list and match with privided list.
   *
   * @return bool|array
   *   TRUE if has correct fields list. FALSE if exception. Array if different.
   */
  public static function checkFieldList($table, array $fields) {

    // Combine fields.
    $fields_must = array_combine($fields, $fields);
    // Current database fields list.
    $fields_curr = [];

    // Get Database connection.
    $con = Database::getConnection();

    // Add table prefix.
    if ($table_prefix = $con->tablePrefix()) {
      $table = $table_prefix . $table;
    }

    try {
      $fields_list = $con->query("DESCRIBE `$table`")->fetchAll();
      foreach ($fields_list as $field) {
        $fields_curr[$field->Field] = $field->Field;
      }

      // Check differents.
      $array_diff = array_diff($fields_must, $fields_curr);
      if (empty($array_diff)) {
        $array_diff = array_diff($fields_curr, $fields_must);
        // Tables matched.
        if (empty($array_diff)) {
          return TRUE;
        }
      }
      // Two tables are different.
      return $array_diff;
    }
    catch (\Exception $ex) {
      return FALSE;
    }

    return FALSE;
  }

}

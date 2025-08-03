<?php

namespace Drupal\entity_update;

/**
 * Entity Update Helper functions.
 */
class EntityUpdateHelper {

  /**
   * Get Configuration Name.
   */
  public static function getConfigName() {
    return 'entity_update.settings';
  }

  /**
   * Get Configuration Object.
   *
   * @param bool $editable
   *   Is editable.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   Configuration object.
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

}

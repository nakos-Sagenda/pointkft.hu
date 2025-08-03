<?php

namespace Drupal\entity_update;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * EntityUpdate Main Class.
 */
class EntityUpdate {

  /**
   * Update all empty entities.
   */
  public static function basicUpdate($force = FALSE) {
    // Check all updateble entities are empty.
    $flgOK = TRUE;
    try {
      $list = self::getEntityTypesToUpdate();
      foreach ($list as $item => $entity_type_changes) {
        if (!empty(\Drupal::entityQuery($item)->accessCheck(FALSE)->execute())) {
          $flgOK = FALSE;
          EntityUpdatePrint::drushLog("The entity '$item' is not empty", 'warning');
        }
      }
    }
    catch (\Exception $e) {
      EntityUpdatePrint::drushLog($e->getMessage(), 'error', [], TRUE);
      $flgOK = FALSE;
    }

    // Return if one of the entity has data.
    if (!$flgOK && !$force) {
      EntityUpdatePrint::drushLog("Cant update as basic, use --all or --force option", 'cancel', [], TRUE);
      EntityUpdatePrint::drushPrint("Example : drush upe --basic --force --nobackup");
      return FALSE;
    }

    // Update.
    try {
      \Drupal::classResolver()
        ->getInstanceFromDefinition(CustomEntityDefinitionUpdateManager::class)
        ->applyUpdates();

      // No need to update = OK.
      return !\Drupal::entityDefinitionUpdateManager()->needsUpdates();
    }
    catch (\Exception $e) {
      EntityUpdatePrint::drushLog($e->getMessage(), 'warning', [], TRUE);
    }
    return FALSE;
  }

  /**
   * Update all entities.
   *
   * @return bool
   *   Update is done or FAIL
   */
  public static function safeUpdateMain(EntityTypeInterface $entity_type = NULL) {

    // Get entitiy types to update.
    $entity_change_summerys = self::getEntityTypesToUpdate();
    if (empty($entity_change_summerys)) {
      EntityUpdatePrint::drushLog('No entities to update.', 'cancel');
      return TRUE;
    }

    // Check entity type to update.
    if ($entity_type) {
      $entity_type_id = $entity_type->id();
      foreach ($entity_change_summerys as $type_id => $entity_type_changes) {
        if ($type_id !== $entity_type_id) {
          unset($entity_change_summerys[$type_id]);
        }
      }

      // Check update for $entity_type_id.
      if (empty($entity_change_summerys)) {
        EntityUpdatePrint::drushLog("No updates for $entity_type_id", 'cancel');
        return TRUE;
      }
      else {
        // Run Update for $entity_type.
        return self::safeUpdateEntityType($entity_type);
      }
      return FALSE;
    }

    // Flags to select install / uninstall methode.
    $flg_has_updated = FALSE;
    $flg_has_install = FALSE;

    // Select the method to use.
    foreach ($entity_change_summerys as $entity_type_changes) {
      foreach ($entity_type_changes as $entity_change_summ) {
        if (strstr($entity_change_summ, "updated")) {
          $flg_has_updated = TRUE;
        }
        elseif (strstr($entity_change_summ, "uninstalled")) {
          $flg_has_updated = TRUE;
        }
        else {
          $flg_has_install = TRUE;
        }
      }
    }

    // Execute Update all entities.
    if (!$flg_has_install && !$flg_has_updated) {
      // Install Or Uninstall not found.
      EntityUpdatePrint::drushLog('Entity Install / Uninstall / Update not found.', 'cancel');
      return FALSE;
    }
    elseif ($flg_has_install && $flg_has_updated) {
      EntityUpdatePrint::drushLog('Has fields to install and to uninstall. Do one action at a time.', 'cancel');
      return FALSE;
    }
    elseif ($flg_has_updated) {
      return self::safeUpdateFields($entity_change_summerys);
    }
    elseif ($flg_has_install) {
      return self::safeUpdateInstallFields();
    }

    EntityUpdatePrint::drushLog('UNKNOWN ERROR.', 'error');
    return FALSE;
  }

  /**
   * Update fields.
   *
   * @return bool
   *   Update is done or FAIL
   */
  private static function safeUpdateFields($entity_change_summerys) {

    // Read and backup data into entity_update entity..
    EntityUpdatePrint::drushPrint("Read and backup data");
    // Backup and delete entities has data, get the data flag.
    $flg_has_data = self::entityUpdateDataBackupDel($entity_change_summerys);

    // Exec Update.
    EntityUpdatePrint::drushPrint("Update entity Schema");
    try {

      \Drupal::classResolver()
        ->getInstanceFromDefinition(CustomEntityDefinitionUpdateManager::class)
        ->applyUpdates();

      EntityUpdatePrint::drushLog('Entities update success', 'ok');
    }
    catch (Exception $ex) {
      EntityUpdatePrint::drushLog($ex->getMessage(), 'warning');
    }

    // Re create.
    if ($flg_has_data) {
      EntityUpdatePrint::drushPrint("Re creating entities.");
      // Restore entities.
      $result = self::entityUpdateDataRestore();

      // Message to Flush 'entity_update' via drush command.
      EntityUpdatePrint::drushLog("Entity recreate Success / End", $result ? 'ok' : 'warning');
      EntityUpdatePrint::drushLog("CAUTION : Before next operation, Flush 'Entity Data' using : drush upe --clean", 'warning');
    }

    // No need to update = OK.
    return !\Drupal::entityDefinitionUpdateManager()->needsUpdates();
  }

  /**
   * Backup and delete entities before an update.
   *
   * @return bool
   *   Has Entity data
   */
  public static function entityUpdateDataBackupDel($entity_change_summerys, $force_type = NULL) {

    // Force a type to backup and delete even no updates.
    if (empty($entity_change_summerys) && $force_type) {
      $entity_change_summerys[$force_type] = 1;
    }
    // Get Database connection.
    $con = Database::getConnection();
    $excludes = EntityUpdateHelper::getConfig()->get('excludes');

    $flg_has_data = FALSE;
    // Read and backup data into entity_update entity..
    EntityUpdatePrint::drushPrint("Read and backup data");
    foreach ($entity_change_summerys as $entity_type_id => $entity_type_changes) {

      // Check entity types excludes.
      if (empty($excludes[$entity_type_id])) {

        EntityUpdatePrint::drushPrint(" - Reading : $entity_type_id");
        $entities = \Drupal::entityTypeManager()
          ->getStorage($entity_type_id)
          ->loadMultiple();
        foreach ($entities as $entity_id => $entity) {
          $entity_type = $entity->getEntityType();

          // Backup entity to DB.
          $con->insert('entity_update')
            ->fields([
              'entity_type' => $entity_type_id,
              'entity_id' => $entity_id,
              'entity_class' => $entity_type->getClass(),
              'status' => 0,
              'data' => Json::encode($entity->toArray()),
            ])
            ->execute();

          // Delete Entity.
          $entity->delete();
          $flg_has_data = TRUE;
        }
        EntityUpdatePrint::drushLog("Backup ok", 'ok');
      }
      else {
        // Exclude by config.
        EntityUpdatePrint::drushLog("Deletion of $entity_type_id is excluded by config.", 'cancel');
      }
    }
    return $flg_has_data;
  }

  /**
   * Restore entities after an update.
   *
   * @return bool
   *   Restore success
   */
  public static function entityUpdateDataRestore() {

    // Get Database connection.
    $con = Database::getConnection();

    $db_data = $con->select('entity_update', 't')
      ->fields('t')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!$db_data) {
      EntityUpdatePrint::drushLog("ERROR, Data read error", 'error');
      return FALSE;
    }

    foreach ($db_data as $row) {

      $entity_class = $row['entity_class'];
      $entity_data = Json::decode($row['data']);

      // Create Entity.
      $entity = $entity_class::create($entity_data);

      // Save entity.
      $entity->save();
    }

    return TRUE;
  }

  /**
   * Update all entities / Install fields.
   *
   * @return bool
   *   Update is done or FAIL
   */
  private static function safeUpdateInstallFields() {
    // Update.
    try {
      // \Drupal::entityDefinitionUpdateManager()->applyUpdates();
      \Drupal::classResolver()
        ->getInstanceFromDefinition(CustomEntityDefinitionUpdateManager::class)
        ->applyUpdates();

      EntityUpdatePrint::drushLog('Entities update success', 'ok');

      // No need to update = OK.
      return !\Drupal::entityDefinitionUpdateManager()->needsUpdates();
    }
    catch (\Exception $e) {
      EntityUpdatePrint::drushLog($e->getMessage(), 'warning');
    }
    return FALSE;
  }

  /**
   * Update an entity type.
   */
  private static function safeUpdateEntityType(EntityTypeInterface $entity_type) {

    // Get entity change summerys.
    $entity_change_summerys = self::getEntityTypesToUpdate($entity_type->id());

    $flg_done = FALSE;
    try {

      // Get entity update manager.
      $update_manager = entity_update_get_entity_definition_update_manager();

      $complete_change_list = $update_manager->getChangeList();

      if ($complete_change_list) {
        $update_manager->entityTypeManager->clearCachedDefinitions();
      }

      foreach ($complete_change_list as $entity_type_id => $change_list) {
        // Update selected entity type only.
        if ($entity_type_id === $entity_type->id()) {

          // Check if has a field to install.
          $flg_has_install = FALSE;
          foreach ($entity_change_summerys[$entity_type_id] as $entity_change_summ) {
            if (strstr($entity_change_summ, "uninstalled")) {
              // Has fields to un install (Nothig todo at the moment).
            }
            else {
              $flg_has_install = TRUE;
            }
          }

          // Backup and delete entities data if no fields to install.
          if (!$flg_has_install) {
            $entity_change_summerys = [$entity_type_id => 1];
            $flg_has_data = self::entityUpdateDataBackupDel($entity_change_summerys);
          }
          else {
            $flg_has_data = FALSE;
          }

          // Process entity types.
          if (!empty($change_list['entity_type'])) {
            // @todo Backup and Restore data via SQL.
            $update_manager->doEntityUpdate($change_list['entity_type'], $entity_type_id);
          }
          // Process field storage definition changes.
          if (!empty($change_list['field_storage_definitions'])) {
            $storage_definitions = $update_manager->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
            $original_storage_definitions = $update_manager->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);

            foreach ($change_list['field_storage_definitions'] as $field_name => $change) {
              $storage_definition = $storage_definitions[$field_name] ?? NULL;
              $original_storage_definition = $original_storage_definitions[$field_name] ?? NULL;
              $update_manager->doFieldUpdate($change, $storage_definition, $original_storage_definition);
            }
          }

          // Re create if necessary.
          if ($flg_has_data) {
            $result = self::entityUpdateDataRestore();
            if ($result) {
              self::cleanupEntityBackup();
            }
          }
          $flg_done = TRUE;
        }
      }
    }
    catch (Exception $e) {
      EntityUpdatePrint::drushLog($e->getMessage(), 'error');
      return FALSE;
    }
    return $flg_done;
  }

  /**
   * Cleanup entity backup database.
   *
   * @return array
   *   The list of entities to update.
   */
  public static function cleanupEntityBackup() {

    // Get Database connection.
    $con = Database::getConnection();
    $con->truncate('entity_update')->execute();

    return TRUE;
  }

  /**
   * Get Entity types to update.
   *
   * @return array
   *   The list of entities to update.
   */
  public static function getEntityTypesToUpdate($type_id = NULL) {

    $list = entity_update_get_entity_changes();

    foreach ($list as $entity_type_id => $entity_change_summery) {
      if (!$type_id || $type_id == $entity_type_id) {
        $list[$entity_type_id] = $entity_change_summery;
      }
    }

    return $list;
  }

}

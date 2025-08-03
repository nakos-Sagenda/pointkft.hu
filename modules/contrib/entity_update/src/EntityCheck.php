<?php

namespace Drupal\entity_update;

/**
 * EntityCheck Main Class.
 */
class EntityCheck {

  /**
   * Get entity types list.
   *
   * @param string $type
   *   The entity type id.
   * @param bool $print
   *   Print to the drush terminal.
   *
   * @return array
   *   The table (Renderer array).
   */
  public static function getEntityTypesList($type = NULL, $print = TRUE) {
    $itmes_list = \Drupal::entityTypeManager()->getDefinitions();

    $table = [
      '#theme' => 'table',
      '#cache' => ['max-age' => 0],
      '#caption' => 'Entity types list',
      '#header' => ['Type ID', 'Type', 'Name'],
      '#rows' => [],
    ];

    foreach ($itmes_list as $itme_name => $item_object) {
      // Excluse if filtred by type.
      if ($type && strstr($itme_name, $type) === FALSE) {
        continue;
      }
      $table['#rows'][] = [
        $itme_name,
        $item_object->getGroup(),
        $item_object->getLabel(),
      ];
    }

    // Print table.
    if ($print) {
      EntityUpdatePrint::drushPrintTable($table);
    }
    return $table;
  }

  /**
   * Get entity list.
   *
   * @param string $type
   *   The entity type id.
   * @param int $start
   *   Start from.
   * @param int $length
   *   The max length.
   * @param bool $print
   *   Print to the drush terminal.
   *
   * @return array
   *   The table (Renderer array).
   */
  public static function getEntityList($type, $start = 0, $length = 10, $print = TRUE) {

    // Cast to integer.
    $start = (int) $start;
    $length = (int) $length;
    $entities = [];

    try {
      // Get entities list.
      $query = \Drupal::entityQuery($type)->accessCheck(FALSE);
      if ($length) {
        $query->range($start, $length);
      }
      $ids = $query->execute();
      $entities = \Drupal::entityTypeManager()->getStorage($type)->loadMultiple($ids);
    }
    catch (\Exception $ex) {
      EntityUpdatePrint::drushLog($ex->getMessage(), 'error');
      return NULL;
    }

    // Create table.
    $table = [
      '#theme' => 'table',
      '#caption' => 'List of the entities : ' . $type,
      '#header' => ['ID', 'Label'],
      '#rows' => [],
    ];
    foreach ($entities as $id => $entiy) {
      $table['#rows'][] = [$id, $entiy->label()];
    }

    // Print table.
    if ($print) {
      EntityUpdatePrint::drushPrintTable($table);
    }
    return $table;
  }

  /**
   * Print entity status to the terminal.
   *
   * @return bool
   *   The entity types are updatable even having data.
   */
  public static function showEntityStatusCli() {

    $flg_updatable = TRUE;
    $esp = "    ";
    $arr = "->  ";
    $list = EntityUpdate::getEntityTypesToUpdate();
    if (empty($list)) {
      EntityUpdatePrint::drushPrint(' -> ALl Entities are up to date');
    }
    else {
      foreach ($list as $item => $entity_type_changes) {
        EntityUpdatePrint::drushPrint(" -> $item . Change(s) : " . count($entity_type_changes));

        $flg_has_install = FALSE;
        $flg_has_uninstall = FALSE;
        $flg_has_update = FALSE;

        // Print change details and check install/uninstall.
        foreach ($entity_type_changes as $entity_change_summ) {
          EntityUpdatePrint::drushPrint($esp . strip_tags($entity_change_summ));
          if (strstr($entity_change_summ, "updated")) {
            $flg_has_update = TRUE;
          }
          elseif (strstr($entity_change_summ, "uninstalled")) {
            $flg_has_uninstall = TRUE;
          }
          else {
            $flg_has_install = TRUE;
          }
        }

        // Print update instruction.
        if ($flg_has_update || $flg_has_install && $flg_has_uninstall) {

          // Check has data.
          if (empty(\Drupal::entityQuery($item)->accessCheck(FALSE)->execute())) {
            EntityUpdatePrint::drushLog("$esp$arr" . "Entity type '$item' is updatable.", 'ok');
            EntityUpdatePrint::drushPrint("$esp$arr" . "Use: drush upe --basic");
          }
          else {
            EntityUpdatePrint::drushLog("$esp$arr" . "Multiple actions detected on '$item'.", 'warning');
            EntityUpdatePrint::drushLog("$esp$arr" . "The entity '$item' is not empty", 'warning');
            EntityUpdatePrint::drushPrint("$esp$arr" . "Try: drush upe --basic --force");
            EntityUpdatePrint::drushPrint("$esp$arr" . "Refer to the documentation");
          }
          $flg_updatable = FALSE;
        }
        else {
          EntityUpdatePrint::drushLog("$esp$arr" . "Entity type '$item' is updatable.", 'ok');
          EntityUpdatePrint::drushPrint("$esp$arr" . "Use: drush upe $item");
        }
        EntityUpdatePrint::drushPrint("");
      }
    }

    return $flg_updatable;
  }

}

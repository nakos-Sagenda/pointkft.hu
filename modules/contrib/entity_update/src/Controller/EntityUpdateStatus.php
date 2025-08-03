<?php

namespace Drupal\entity_update\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\entity_update\EntityCheck;
use Drupal\entity_update\EntityUpdate;

/**
 * Entity Update status displays.
 */
class EntityUpdateStatus extends ControllerBase {

  /**
   * Get entity types list.
   */
  public function entityTypes() {

    $output = [];
    $output['#cache']['max-age'] = 0;

    $entity_types = EntityCheck::getEntityTypesList(NULL, FALSE);
    $output['data_table'] = $entity_types;

    return $output;
  }

  /**
   * Get entity types status.
   */
  public function entityStatus() {

    $output = [];
    $output['#cache']['max-age'] = 0;

    $list = EntityUpdate::getEntityTypesToUpdate();
    if (empty($list)) {
      $output[] = [
        '#type' => 'markup',
        '#markup' => "All Entities are up to date.",
        '#prefix' => '<div><b><i>',
        '#suffix' => '</i></b></div>',
      ];
    }
    else {

      foreach ($list as $item => $entity_type_changes) {
        $count = count($entity_type_changes);
        $table = [
          '#theme' => 'table',
          '#cache' => ['max-age' => 0],
          '#caption' => "Change of the entity '$item' ($count)",
          '#header' => ['Type ID', 'Type', 'Name'],
          '#rows' => [],
        ];

        foreach ($entity_type_changes as $ec_key => $entity_change_summ) {
          $table['#rows'][] = [$ec_key, $entity_change_summ];
        }

        $output['tables'][] = $table;
      }
    }

    return $output;
  }

}

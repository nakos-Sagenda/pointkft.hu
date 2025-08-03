<?php

namespace Drupal\entity_update_tests\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines a test entity.
 *
 * @ingroup entity_update
 *
 * @ConfigEntityType(
 *   id = "entity_update_tests_cfg",
 *   label = @Translation("Entity Update Tests Configuration Entity"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class EntityUpdateTestsConfigEntity extends ConfigEntityBase {

  /**
   * The entity ID.
   *
   * @var string
   */
  public $id;

  /**
   * The entity UUID.
   *
   * @var string
   */
  public $uuid;

  /**
   * The label.
   *
   * @var string
   */
  public $label;

}

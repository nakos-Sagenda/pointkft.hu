<?php

namespace Drupal\entity_update_tests\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_update_tests\EntityUpdateTestHelper;

/**
 * Defines the Paragraph entity.
 *
 * @ingroup entity_update
 *
 * @ContentEntityType(
 *   id = "entity_update_tests_c02",
 *   label = @Translation("Entity Update Tests Content Entity 02"),
 *   base_table = "entity_update_tests_c02",
 *   entity_keys = {
 *     "id" = "id",
 *   }
 * )
 */
class EntityUpdateTestsContentEntity02 extends ContentEntityBase {

  /**
   * Get configurable fields list.
   */
  public static function getConfigurableFields($mode = NULL) {

    $list = [];

    // Install / Uninstall fields.
    if ($mode == 'install' || !$mode) {
      $list['city'] = 'City';
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setCardinality(10)
      ->setDescription(t('The UUID of the entity Changed.'))
      ->setReadOnly(TRUE);

    // Dynamic name field to simulation deletation and creation.
    if (EntityUpdateTestHelper::fieldStatus('city')) {
      $fields['city'] = BaseFieldDefinition::create('string')
        ->setLabel(t('City'))
        ->setSettings(['max_length' => 100, 'text_processing' => 0]);
    }

    return $fields;
  }

}

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
 *   id = "entity_update_tests_cnt",
 *   label = @Translation("Entity Update Tests Content Entity"),
 *   base_table = "entity_update_tests_cnt",
 *   entity_keys = {
 *     "id" = "id",
 *   }
 * )
 */
class EntityUpdateTestsContentEntity extends ContentEntityBase {

  /**
   * Get configurable fields list.
   */
  public static function getConfigurableFields($mode = NULL) {
    $list = [];

    // Install / Uninstall fields.
    if ($mode == 'install' || !$mode) {
      $list['name'] = 'Name';
      $list['description'] = 'Description';
    }

    // Change field type.
    if ($mode == 'type' || !$mode) {
      $list['type'] = 'Type';
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Fix field.
    $fields['fix'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fix field.'))
      ->setSettings(['max_length' => 10, 'text_processing' => 0]);

    // Dynamic name field to simulation deletation and creation.
    if (EntityUpdateTestHelper::fieldStatus('name')) {
      $fields['name'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Name'))
        ->setSettings(['max_length' => 100, 'text_processing' => 0]);
    }

    // Dynamic description field to simulation deletation and creation.
    if (EntityUpdateTestHelper::fieldStatus('description')) {
      $fields['description'] = BaseFieldDefinition::create('string_long')
        ->setLabel(t('Description'))
        ->setSettings(['text_processing' => 0]);
    }

    // Dynamic type field to simulate file type update.
    $type = EntityUpdateTestHelper::fieldStatus('type');
    $types = ['string', 'integer'];
    if (in_array($type, $types)) {
      $fields['type'] = BaseFieldDefinition::create($type)
        ->setLabel(t('Type'))
        ->setSettings(['max_length' => 10, 'text_processing' => 0]);
    }

    return $fields;
  }

}

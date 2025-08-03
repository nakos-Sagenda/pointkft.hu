<?php

namespace Drupal\gdpr_tasks\Traversal;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\EntityTraversal;
use function implode;
use function in_array;
use function pathinfo;

/**
 * Entity traversal for performing Right to Access requests.
 *
 * @package Drupal\gdpr_tasks
 */
class RightToAccessEntityTraversal extends EntityTraversal {

  /**
   * Assets.
   *
   * @var array
   */
  private $assets = [];

  /**
   * {@inheritdoc}
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, GdprField $parent_config = NULL) {
    $entityType = $entity->getEntityTypeId();

    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $entity->bundle());
    $fieldConfigs = $config->getFieldsForBundle($entity->bundle());

    foreach ($fields as $fieldId => $field) {
      $fieldConfig = $fieldConfigs[$fieldId] ?? NULL;

      // If the field is not configured, not enabled,
      // or not enabled for RTA, then skip it.
      if ($fieldConfig === NULL ||
      !$fieldConfig->enabled ||
      !in_array($fieldConfig->rta, ['inc', 'maybe'])) {
        continue;
      }

      $pluginName = "{$entityType}|{$entity->bundle()}|{$fieldId}";

      $filename = 'main';
      if ($parent_config) {
        $filename = !empty($parent_config->sarsFilename) ? $parent_config->sarsFilename : $filename;
      }

      $fieldValue = $this->getFieldValue($entity, $field, $fieldId);

      $data = [
        'plugin_name' => $pluginName,
        'entity_type' => $entityType,
        'entity_id' => $entity->id(),
        'file' => $filename,
        'row_id' => $row_id,
        'label' => $field->getLabel(),
        'value' => $fieldValue,
        'notes' => $fieldConfig->notes,
        'rta' => $fieldConfig->rta,
      ];

      $this->results["{$pluginName}|{$entity->id()}"] = $data;
    }
  }

  /**
   * Gets the field value, taking into account file references.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The current entity being processed.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   Field definition.
   * @param string $fieldId
   *   Field ID.
   *
   * @return string
   *   Field value
   */
  protected function getFieldValue(FieldableEntityInterface $entity, FieldDefinitionInterface $field, $fieldId) {
    // Special handling for file references.
    // For files, we want to add to the assets collection.
    $labels = [];

    if ($entity->{$fieldId} instanceof EntityReferenceFieldItemList) {
      if ($field->getSetting('target_type') === 'file') {
        /** @var \Drupal\file\Entity\File $file */
        foreach ($entity->{$fieldId}->referencedEntities() as $file) {
          $this->assets[] = ['target_id' => $file->id(), 'display' => 1];
          $labels[] = "assets/{$file->id()}." . pathinfo($file->getFileUri(), PATHINFO_EXTENSION);
        }
      }
      else {
        /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
        foreach ($entity->{$fieldId}->referencedEntities() as $referenced_entity) {
          if ($referenced_entity->label()) {
            $labels[] = "{$referenced_entity->label()} [{$referenced_entity->id()}]";
          }
          else {
            $labels[] = $referenced_entity->id();
          }
        }
      }
    }
    else {
      $labels[] = $entity->get($fieldId)->getString();
    }

    return implode(', ', $labels);
  }

}

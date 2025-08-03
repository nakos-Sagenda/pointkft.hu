<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use function in_array;
use function stripos;

/**
 * Defines a helper class for stuff related to views data.
 */
class GDPRCollector {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;


  /**
   * Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $bundleInfo;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info.
   */
  public function __construct(
    EntityTypeManager $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    EntityTypeBundleInfoInterface $bundleInfo
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->bundleInfo = $bundleInfo;
  }

  /**
   * List fields on entity including their GDPR values.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type id.
   * @param string $bundleId
   *   The entity bundle id.
   * @param array $filters
   *   Array of filters with following keys:
   *   'empty' => filter out entities where all fields are not configured.
   *   'rtf' => only include fields where RTF is configured.
   *   'rta' => only include fields where RTA is configured.
   *   'search' => only include fields whose name match.
   *
   * @return array
   *   GDPR entity field list.
   */
  public function listFields(EntityTypeInterface $entityType, $bundleId, array $filters) {
    $bundleType = $entityType->getBundleEntityType();
    $gdprSettings = GdprFieldConfigEntity::load($entityType->id());

    // @todo explicitly skip commerce_order_item for now as they break bundles
    if ($entityType->id() == 'commerce_order_item') {
      return [];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType->id(), $bundleId);

    // Get fields for entity.
    $fields = [];

    // If the 'Filter out entities where all fields are not configured' option
    // is set, return an empty array if GDPR is not configured for the entity.
    if ($filters['empty'] && $gdprSettings === NULL) {
      return $fields;
    }

    $hasAtLeastOneConfiguredField = FALSE;

    foreach ($fieldDefinitions as $fieldId => $fieldDefinition) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $fieldDefinition */
      $key = "{$entityType->id()}.$bundleId.$fieldId";
      $route_name = 'gdpr_fields.edit_field';
      $route_params = [
        'entity_type' => $entityType->id(),
        'bundle_name' => $bundleId,
        'field_name' => $fieldId,
      ];

      if (isset($bundleType)) {
        $route_params[$bundleType] = $bundleId;
      }

      $rta = '0';
      $rtf = '0';

      $label = $fieldDefinition->getLabel();

      // If we're searching by name, check if the label matches search.
      if ($filters['search'] && (!stripos($label, $filters['search']) || !stripos($fieldDefinition->getName(), $filters['search']))) {
        continue;
      }

      $is_id = $entityType->getKey('id') === $fieldId;

      $fields[$key] = [
        'title' => $label,
        'type' => $is_id ? 'primary_key' : $fieldDefinition->getType(),
        'rta' => 'Not Configured',
        'rtf' => 'Not Configured',
        'notes' => '',
        'edit' => '',
        'is_id' => $is_id,
      ];

      if ($entityType->get('field_ui_base_route')) {
        $fields[$key]['edit'] = Link::createFromRoute('edit', $route_name, $route_params);
      }

      if ($gdprSettings !== NULL) {
        /** @var \Drupal\gdpr_fields\Entity\GdprField $field_settings */
        $field_settings = $gdprSettings->getField($bundleId, $fieldId);
        if ($field_settings->enabled) {
          $hasAtLeastOneConfiguredField = TRUE;
          $rta = $field_settings->rta;
          $rtf = $field_settings->rtf;

          $fields[$key]['rta'] = $field_settings->rtaDescription();
          $fields[$key]['rtf'] = $field_settings->rtfDescription();
          $fields[$key]['notes'] = $field_settings->notes;
        }
      }

      // Apply filters.
      if (!empty($filters['rtf']) && !in_array($rtf, $filters['rtf'], FALSE)) {
        unset($fields[$key]);
      }

      if (!empty($filters['rta']) && !in_array($rta, $filters['rta'], FALSE)) {
        unset($fields[$key]);
      }
    }

    // Handle the 'Filter out Entities where all fields are not configured'
    // checkbox.
    if ($filters['empty'] && !$hasAtLeastOneConfiguredField) {
      return [];
    }

    return $fields;
  }

  /**
   * Gets bundles belonging to an entity type.
   *
   * @param string $entityType_id
   *   The entity type for which bundles should be located.
   *
   * @return array
   *   Array of bundles.
   */
  public function getBundles($entityType_id) {
    $all_bundles = $this->bundleInfo->getAllBundleInfo();
    $bundles = $all_bundles[$entityType_id] ?? [$entityType_id => []];
    return $bundles;
  }

}

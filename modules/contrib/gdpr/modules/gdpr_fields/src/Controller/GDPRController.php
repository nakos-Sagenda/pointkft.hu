<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gdpr_fields\Form\GdprFieldFilterForm;
use Drupal\gdpr_fields\GDPRCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use function count;
use function in_array;
use function ksort;

/**
 * Returns responses for GDPR Field routes.
 */
class GDPRController extends ControllerBase {

  /**
   * Stores the Views data cache object.
   *
   * @var \Drupal\gdpr_fields\GDPRCollector
   */
  protected $collector;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\gdpr_fields\GDPRCollector $collector
   *   The GDPR collector service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   HTTP Request stack.
   */
  public function __construct(GDPRCollector $collector, RequestStack $request_stack) {
    $this->collector = $collector;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gdpr_fields.collector'),
      $container->get('request_stack')
    );
  }

  /**
   * Lists all fields with GDPR sensitivity.
   *
   * @return array
   *   The Views plugins report page.
   */
  public function fieldsList() {
    $filters = GdprFieldFilterForm::getFilters($this->request);

    $output = [];
    $output['filter'] = $this->formBuilder()->getForm(GdprFieldFilterForm::class);
    $output['#attached']['library'][] = 'gdpr_fields/field-list';

    foreach ($this->entityTypeManager()->getDefinitions() as $entityTypeId => $definition) {
      // Skip non-fieldable/config entities.
      if (!$definition->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }

      // If a filter is active, exclude any entities that don't match.
      if (!empty($filters['entity_type']) && !in_array($entityTypeId, $filters['entity_type'], FALSE)) {
        continue;
      }

      $bundles = $this->collector->getBundles($entityTypeId);

      $output[$entityTypeId] = [
        '#type' => 'details',
        '#title' => $definition->getLabel() . " [$entityTypeId]",
        '#open' => TRUE,
      ];

      if (count($bundles) > 1) {
        $atLeastOneBundleHasFields = FALSE;
        foreach ($bundles as $bundle_id => $bundle_info) {
          $fieldTable = $this->buildFieldTable($definition, $bundle_id, $filters);

          if ($fieldTable) {
            $atLeastOneBundleHasFields = TRUE;
            $output[$entityTypeId][$bundle_id] = [
              '#type' => 'details',
              '#title' => new TranslatableMarkup('%label [%bundle]', [
                '%label' => $bundle_info['label'],
                '%bundle' => $bundle_id,
              ]),
              '#open' => TRUE,
            ];
            $output[$entityTypeId][$bundle_id]['fields'] = $fieldTable;
          }
        }

        if (!$atLeastOneBundleHasFields) {
          unset($output[$entityTypeId]);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = $entityTypeId;
        $fieldTable = $this->buildFieldTable($definition, $bundle_id, $filters);
        if ($fieldTable) {
          $output[$entityTypeId][$bundle_id]['fields'] = $fieldTable;
        }
        else {
          // If the entity has no fields because they've been filtered out
          // don't bother including it.
          unset($output[$entityTypeId]);
        }
      }
    }

    return $output;
  }

  /**
   * Build a table for entity field list.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param array $filters
   *   Filters.
   *
   * @return array
   *   Renderable array for field list table.
   */
  protected function buildFieldTable(EntityTypeInterface $entity_type, $bundle_id, array $filters) {
    $rows = $this->collector->listFields($entity_type, $bundle_id, $filters);

    if (count($rows) === 0) {
      return NULL;
    }

    // Sort rows by field name.
    ksort($rows);

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Type'),
        $this->t('Right to access'),
        $this->t('Right to be forgotten'),
        $this->t('Notes'),
        '',
      ],
      '#sticky' => TRUE,
    ];

    $delta = 0;
    foreach ($rows as $row) {
      $table[$delta]['title'] = [
        '#plain_text' => $row['title'],
      ];

      $type_markup = $row['is_id'] || $row['type'] === 'entity_reference' ? "<strong>{$row['type']}</strong>" : $row['type'];

      $table[$delta]['type'] = [
        '#markup' => new FormattableMarkup($type_markup, []),
      ];

      $table[$delta]['rta'] = [
        '#plain_text' => $row['rta'],
      ];

      $table[$delta]['rtf'] = [
        '#plain_text' => $row['rtf'],
      ];

      $table[$delta]['notes'] = [
        '#markup' => empty($row['notes']) ? '' : '<span class="notes" data-icon="?"></span><div>' . $row['notes'] . '</div>',
      ];

      $table[$delta]['edit'] = [
        '#markup' => !empty($row['edit']) ? $row['edit']->toString() : '',
      ];

      ++$delta;
    }

    return $table;
  }

}

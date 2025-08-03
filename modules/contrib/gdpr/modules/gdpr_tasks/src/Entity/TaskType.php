<?php

namespace Drupal\gdpr_tasks\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Task type entity.
 *
 * @ConfigEntityType(
 *   id = "gdpr_task_type",
 *   label = @Translation("Task type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\gdpr_tasks\TaskTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\gdpr_tasks\Form\TaskTypeForm",
 *       "edit" = "Drupal\gdpr_tasks\Form\TaskTypeForm",
 *       "delete" = "Drupal\gdpr_tasks\Form\TaskTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\gdpr_tasks\TaskTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "gdpr_task_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "gdpr_task",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/config/gdpr/tasks/types",
 *     "add-form" = "/admin/config/gdpr/tasks/types/add",
 *     "edit-form" = "/admin/config/gdpr/tasks/types/{gdpr_task_type}/edit",
 *     "delete-form" = "/admin/config/gdpr/tasks/types/{gdpr_task_type}/delete"
 *   }
 * )
 */
class TaskType extends ConfigEntityBundleBase implements TaskTypeInterface {

  /**
   * The Task type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Task type label.
   *
   * @var string
   */
  protected $label;

}

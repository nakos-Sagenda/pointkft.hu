<?php

namespace Drupal\entity_update_tests\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_update_tests\Entity\EntityUpdateTestsContentEntity;
use Drupal\entity_update_tests\Entity\EntityUpdateTestsContentEntity02;
use Drupal\entity_update_tests\EntityUpdateTestHelper;

/**
 * Class CheckEntityUpdate.
 *
 * @package Drupal\entity_update\Form
 *
 * @ingroup entity_update
 */
class EntityUpdateTestSettings extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'entity_update_tests';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $link_help = '/admin/help/entity_update';
    $form['messages']['about'] = [
      '#type' => 'markup',
      '#markup' => "<a href='$link_help'>Help page</a>.",
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    // Unstall / Uninstall fields.
    $fields = self::getConfigurableFields('install');
    foreach ($fields as $field_key => $field_name) {
      $form['fields'][$field_key] = [
        '#type' => 'checkbox',
        '#title' => "Field : $field_name",
        '#default_value' => EntityUpdateTestHelper::fieldStatus($field_key),
      ];
    }

    // Cha,nge field type.
    $fields = self::getConfigurableFields('type');
    foreach ($fields as $field_key => $field_name) {
      $form['fields'][$field_key] = [
        '#type' => 'select',
        '#options' => ['string' => $this->t('String'), 'integer' => $this->t('Integer')],
        '#title' => "Field : $field_name",
        '#default_value' => EntityUpdateTestHelper::fieldStatus($field_key),
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fields = self::getConfigurableFields();
    foreach ($fields as $field_key => $field_name) {
      $value = $form_state->getValue($field_key);
      $this->messenger()->addMessage("$field_name : ($value) " . ($value ? "TRUE" : "FALSE"));
      EntityUpdateTestHelper::fieldEnable($field_key, $value);
    }
    $this->messenger()->addMessage($this->t("Test entity configuration success"));
  }

  /**
   * {@inheritdoc}
   */
  private static function getConfigurableFields($mode = NULL) {
    $fields = EntityUpdateTestsContentEntity::getConfigurableFields($mode);
    $fields += EntityUpdateTestsContentEntity02::getConfigurableFields($mode);
    return $fields;
  }

}

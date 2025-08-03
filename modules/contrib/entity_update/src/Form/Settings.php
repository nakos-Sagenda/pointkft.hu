<?php

namespace Drupal\entity_update\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_update\EntityUpdateHelper;

/**
 * Entity update settings form.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_update_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [EntityUpdateHelper::getConfigName()];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config(EntityUpdateHelper::getConfigName());

    $items_list = entity_update_get_entity_definitions();
    $options = [];
    foreach ($items_list as $key => $item) {
      $group = $item->getGroup();
      $label = $item->getLabel();
      $options[$key] = $label . " ($group)";
    }

    $form['excludes'] = [
      '#type' => 'checkboxes',
      '#title' => 'Entity types to exclude',
      '#description' => $this->t('Selected entity types are exclude from deletation and recreation.'),
      '#options' => $options,
      '#default_value' => $config->get('excludes'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => "Save Settings",
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $list = [
      'excludes',
    ];
    $config = $this->config(EntityUpdateHelper::getConfigName());
    foreach ($list as $item) {
      $config->set($item, $form_state->getValue($item));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

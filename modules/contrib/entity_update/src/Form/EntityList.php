<?php

namespace Drupal\entity_update\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_update\EntityCheck;

/**
 * Displays list of Entities that needs to be updated.
 *
 * @package Drupal\entity_update\Form
 *
 * @ingroup entity_update
 */
class EntityList extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'entity_update_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = '', $start = '', $length = '') {

    $link_help = '/admin/help/entity_update';
    $form['messages']['about'] = [
      '#type' => 'markup',
      '#markup' => "Please refer to the <a href='$link_help'>Help page</a>.",
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    $entity_types = EntityCheck::getEntityTypesList(NULL, FALSE);
    $form['filters'] = [
      '#type' => "fieldset",
      '#title' => "Filters",
      '#open' => TRUE,
    ];
    $options = [];
    foreach ($entity_types['#rows'] as $row) {
      $options[$row[0]] = $row[2];
    }
    $form['filters']['entity_type_id'] = [
      '#title' => 'The entity type',
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $entity_type_id,
    ];
    $form['filters']['start'] = [
      '#title' => 'Start from',
      '#type' => 'number',
      '#default_value' => $start,
      '#step' => 1,
      '#min' => 0,
    ];
    $form['filters']['length'] = [
      '#title' => 'Maximum number of result',
      '#type' => 'number',
      '#default_value' => $length,
      '#step' => 1,
      '#min' => 1,
    ];

    $form['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Show'),
    ];

    // Show Entities list.
    if ($entity_type_id) {
      $entities = EntityCheck::getEntityList($entity_type_id, $start, $length, FALSE);
      $form['result']['result'] = $entities;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $entity_type_id = $form_state->getValue('entity_type_id');
    $start = $form_state->getValue('start');
    $length = $form_state->getValue('length');
    // Redirect to correct page with parameters.
    if ($entity_type_id) {
      $url = Url::fromRoute('entity_update.list', [
        'entity_type_id' => $entity_type_id,
        'start' => $start,
        'length' => $length,
      ]);
      $form_state->setRedirectUrl($url);
    }
  }

}

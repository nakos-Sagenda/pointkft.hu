<?php

namespace Drupal\pdf\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pdf.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pdf.settings');
    $form['custom_viewer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to custom viewer.html file'),
      '#description' => $this->t('Specify a custom viewer.html file. This should be a full path starting with a slash. If left empty, the default viewer.html file will be used.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('custom_viewer'),
      '#attributes' => [
        'placeholder' => $this->t('example: /sites/default/themes/yourtheme/pdfjs/viewer.html'),
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('pdf.settings')
      ->set('custom_viewer', $form_state->getValue('custom_viewer'))
      ->save();
  }

}

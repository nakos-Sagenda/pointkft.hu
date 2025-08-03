<?php

namespace Drupal\gdpr_compliance\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the form controller.
 */
class SettingsPopup extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_compliance';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gdpr_compliance.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gdpr_compliance.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('Display pop-up'),
      '#open' => TRUE,
    ];
    $form["general"]['popup-guests'] = [
      '#title' => $this->t('Display for guests'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('popup-guests'),
    ];
    $form["general"]['popup-users'] = [
      '#title' => $this->t('Display for authenticated users'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('popup-users'),
    ];
    $form["general"]['popup-position'] = [
      '#title' => $this->t('Popup position'),
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $config->get('popup-position'),
    ];
    $form["general"]['popup-morelink'] = [
      '#title' => $this->t('Url for [More information] button.'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('popup-morelink'),
      '#description' => $this->t('Relative path starts with "/", or absolute start with http/https.'),
    ];

    $form["text"] = [
      '#type' => 'details',
      '#title' => $this->t('Text'),
      '#open' => FALSE,
    ];
    $defaults = [
      'text-cookies' => $this->t("We use cookies on our website to support technical features that enhance your user experience."),
      'text-analytics' => $this->t("We also use analytics & advertising services. To opt-out click for more information."),
      'btn-agree' => $this->t("I've read it"),
      'btn-findmore' => $this->t("More information"),
    ];
    $form["text"]['popup-text-cookies'] = [
      '#title' => $this->t('Line 1 (cookies)'),
      '#type' => 'textfield',
      '#default_value' => $config->get('popup-text-cookies'),
      '#description' => $this->t('Leave blank to use default: <i>@default</i>', ['@default' => $defaults['text-cookies']]),
      '#maxlength' => 255,
    ];
    $form["text"]['popup-text-analytics'] = [
      '#title' => $this->t('Line 2 (analytics)'),
      '#type' => 'textfield',
      '#default_value' => $config->get('popup-text-analytics'),
      '#description' => $this->t('Leave blank to use default: <i>@default</i>', ['@default' => $defaults['text-analytics']]),
      '#maxlength' => 255,
    ];
    $form["text"]['popup-btn-agree'] = [
      '#title' => $this->t('Agree button'),
      '#type' => 'textfield',
      '#default_value' => $config->get('popup-btn-agree'),
      '#description' => $this->t('Leave blank to use default: <i>@default</i>', ['@default' => $defaults['btn-agree']]),
    ];
    $form["text"]['popup-btn-findmore'] = [
      '#title' => $this->t('Find-more button'),
      '#type' => 'textfield',
      '#default_value' => $config->get('popup-btn-findmore'),
      '#description' => $this->t('Leave blank to use default: <i>@default</i>', ['@default' => $defaults['btn-findmore']]),
    ];
    $form['color'] = [
      '#type' => 'details',
      '#title' => $this->t('Color'),
      '#open' => TRUE,
    ];
    $form['color']['popup-custom-color'] = [
      '#title' => $this->t('Use custom colors'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('popup-custom-color'),
      '#description' => $this->t('Set color by color-widget of use hex value.'),
    ];
    $form['color']['popup-info'] = [
      '#markup' => '<h3>' . $this->t('Pop-up background color:') . '</h3>',
    ];
    $form["color"]['popup-color'] = [
      '#title' => $this->t('Color'),
      '#type' => 'color',
      '#default_value' => $config->get('popup-color'),
      '#description' => $this->t(
        'Text will be inversed. Now text is @color.',
        ['@color' => $config->get('popup-text')]
      ),
    ];
    $form["color"]['popup-hex'] = [
      '#title' => $this->t('Color HEX'),
      '#type' => 'textfield',
      '#placeholder' => $config->get('popup-color'),
      '#description' => $this->t('Background has opacity 0.9'),
    ];
    $form['color']['button-info'] = [
      '#markup' => '<h3>' . $this->t('Button color:') . '</h3>',
    ];
    $form["color"]['button-color'] = [
      '#title' => $this->t('Color'),
      '#type' => 'color',
      '#default_value' => $config->get('button-color'),
    ];
    $form["color"]['button-hex'] = [
      '#title' => $this->t('Color HEX'),
      '#type' => 'textfield',
      '#placeholder' => $config->get('button-color'),
      '#description' => $this->t(
        'Text will be inversed. Now text is @color.',
        ['@color' => $config->get('button-text')]
      ),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements a form submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gdpr_compliance.settings');
    $popup_color = $form_state->getValue('popup-color');
    if ($popup_hex = $this->tryHex($form_state->getValue('popup-hex'))) {
      $popup_color = $popup_hex;
    }
    $button_color = $form_state->getValue('button-color');
    if ($button_hex = $this->tryHex($form_state->getValue('button-hex'))) {
      $button_color = $button_hex;
    }
    $config
      ->set('popup-users', $form_state->getValue('popup-users'))
      ->set('popup-guests', $form_state->getValue('popup-guests'))
      ->set('popup-position', $form_state->getValue('popup-position'))
      ->set('popup-morelink', $form_state->getValue('popup-morelink'))
      ->set('popup-text-cookies', $form_state->getValue('popup-text-cookies'))
      ->set('popup-text-analytics', $form_state->getValue('popup-text-analytics'))
      ->set('popup-btn-agree', $form_state->getValue('popup-btn-agree'))
      ->set('popup-btn-findmore', $form_state->getValue('popup-btn-findmore'))
      ->set('popup-custom-color', $form_state->getValue('popup-custom-color'))
      ->set('popup-color', $popup_color)
      ->set('popup-text', $this->getColorContrastInverse($popup_color))
      ->set('button-color', $button_color)
      ->set('button-text', $this->getColorContrastInverse($button_color))
      ->save();
  }

  /**
   * Try hex.
   */
  private function tryHex($color) {
    $result = FALSE;
    $hex = str_replace('#', '', trim($color));
    if (strlen($hex) == 6 && ctype_xdigit($hex)) {
      $result = "#$hex";
    }
    return $result;
  }

  /**
   * Inverse check.
   */
  private function getColorContrastInverse($color) {
    if ($color == 'none') {
      $color = '#306133';
    }
    $hexcolor = substr($color, 1);
    $r = hexdec(substr($hexcolor, 0, 2));
    $g = hexdec(substr($hexcolor, 2, 2));
    $b = hexdec(substr($hexcolor, 4, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? 'black' : 'white';
  }

}

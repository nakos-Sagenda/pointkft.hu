<?php

namespace Drupal\gdpr_compliance\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_compliance\Utility\FormWarning;

/**
 * Hook hook_form_contact_message_form_alter().
 */
class FormContactMessageFormAlter {

  /**
   * Hook.
   */
  public static function hook(&$form, FormStateInterface $form_state, $form_id) {
    $display = FALSE;
    $config = \Drupal::config('gdpr_compliance.settings');
    if ($config->get('contact_message-mode') == 'all') {
      $display = TRUE;
    }
    elseif ($config->get('contact_message-mode') == 'custom') {
      $bundles = $config->get('contact_message-bundles');
      $formkey = substr($form_id, 16, -5);
      if (isset($bundles[$formkey]) && $bundles[$formkey]) {
        $display = TRUE;
      }
    }
    if ($display) {
      FormWarning::addWarning($form);
    }
  }

}

<?php

namespace Drupal\gdpr_compliance\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_compliance\Utility\FormWarning;

/**
 * Hook hook_form_node_alter().
 */
class FormNodeFormAlter {

  /**
   * Hook.
   */
  public static function hook(&$form, FormStateInterface $form_state, $form_id) {
    $display = FALSE;
    $config = \Drupal::config('gdpr_compliance.settings');
    if ($config->get('node-mode') == 'all') {
      $display = TRUE;
    }
    elseif ($config->get('node-mode') == 'custom') {
      $bundles = $config->get('node-bundles');
      $formkey = substr($form_id, 5, -5);
      if (isset($bundles[$formkey]) && $bundles[$formkey]) {
        $display = TRUE;
      }
    }
    if ($display) {
      FormWarning::addWarning($form);
    }
  }

}

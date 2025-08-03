<?php

namespace Drupal\gdpr_compliance\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_compliance\Utility\FormWarning;

/**
 * Hook hook_form_webform_submission_form_alter().
 */
class FormWebformSubmissionFormAlter {

  /**
   * Hook.
   */
  public static function hook(&$form, FormStateInterface $form_state, $form_id) {
    $display = FALSE;
    $config = \Drupal::config('gdpr_compliance.settings');
    if ($config->get('webform-mode') == 'all') {
      $display = TRUE;
    }
    elseif ($config->get('webform-mode') == 'custom') {
      $bundles = $config->get('webform-bundles');
      $formkey = substr($form_id, 19, -9);
      if (isset($bundles[$formkey]) && $bundles[$formkey]) {
        $display = TRUE;
      }
    }
    if ($display) {
      FormWarning::addWarning($form);
    }
  }

}

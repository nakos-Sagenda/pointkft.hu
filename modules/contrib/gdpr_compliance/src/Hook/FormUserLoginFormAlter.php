<?php

namespace Drupal\gdpr_compliance\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_compliance\Utility\FormWarning;

/**
 * Hook hook_form_user_login_alter().
 */
class FormUserLoginFormAlter {

  /**
   * Hook.
   */
  public static function hook(&$form, FormStateInterface $form_state, $form_id) {
    if (\Drupal::config('gdpr_compliance.settings')->get('user-login')) {
      FormWarning::addWarning($form);
    };
  }

}

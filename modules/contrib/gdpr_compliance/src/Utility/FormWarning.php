<?php

namespace Drupal\gdpr_compliance\Utility;

/**
 * @file
 * Contains \Drupal\gdpr_compliance\Utility\FormWarning.
 */
use Drupal\Core\Url;

/**
 * FormWarning helpers.
 */
class FormWarning {

  /**
   * Add "Cookie & Privacy Policy" warning.
   */
  public static function addWarning(&$form) {
    // Generate url for 'More information' link.
    $link = \Drupal::config('gdpr_compliance.settings')->get('from-morelink');
    if (substr($link, 0, 1) == '/') {
      // A path should be handled as user input.
      $url = Url::fromUserInput($link);
    }
    else {
      // An external url should use 'fromUri'.
      $url = Url::fromUri($link);
    }
    // Check 'admin/people' & administer_users permission.
    $is_adminpath = \Drupal::request()->getRequestUri() == '/admin/people/create';
    if (!$is_adminpath && empty($form['administer_users']['#value'])) {
      $form['gdpr-warning'] = [
        '#type' => 'checkbox',
        '#title' => t("I have read and agree to the Cookie & Privacy Policy"),
        '#default_value' => FALSE,
        '#required' => TRUE,
        '#attributes' => [
          'required' => 'required',
        ],
        '#description' => t(
          "<a href='@href' target='_blank'>Cookie & Privacy Policy for Website</a>",
          ['@href' => $url->toString()]
        ),
        '#weight' => 99,
      ];
    }
  }

}

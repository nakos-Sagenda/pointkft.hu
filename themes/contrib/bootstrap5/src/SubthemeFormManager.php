<?php

namespace Drupal\bootstrap5;

use Drupal\Core\Form\FormStateInterface;

/**
 * Bootstrap5 subtheme form manager.
 */
class SubthemeFormManager {

  /**
   * The subtheme manager.
   *
   * @var \Drupal\bootstrap5\SubthemeManager
   */
  protected SubthemeManager $subthemeManager;

  /**
   * SubthemeFormManager constructor.
   */
  public function __construct() {
    $this->subthemeManager = new SubthemeManager(\Drupal::service('file_system'), \Drupal::service('messenger'));
  }

  /**
   * Validate callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see hook_form_alter()
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $result = $this->subthemeManager->validateSubtheme($form_state->getValue('subtheme_folder'), $form_state->getValue('subtheme_machine_name'));

    if (is_array($result)) {
      $form_state->setErrorByName(...$result);
    }
  }

  /**
   * Submit callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see hook_form_alter()
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Create subtheme.
    $themeMName = $form_state->getValue('subtheme_machine_name');
    $themeName = $form_state->getValue('subtheme_name');
    $subthemePathValue = $form_state->getValue('subtheme_folder');
    if (empty($themeName)) {
      $themeName = $themeMName;
    }

    $this->subthemeManager->createSubtheme($themeMName, $subthemePathValue, $themeName);
  }

}

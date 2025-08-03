<?php

namespace Drupal\entity_update\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_update\EntityUpdate;

/**
 * Class CheckEntityUpdate.
 *
 * @package Drupal\entity_update\Form
 *
 * @ingroup entity_update
 */
class EntityUpdateExec extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'entity_update_exec';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $action = 'default') {

    $aboutTextDiff = 'This page allows you to <b>update the entity types schema</b>.';
    if ($action == 'clean') {
      $aboutTextDiff = 'This page allows you to <b>cleanup the backup table</b>.';
    }
    elseif ($action == 'rescue') {
      $aboutTextDiff = 'This page allows you to <b>restore entities from backup table</b>.';
    }
    elseif ($action == 'type') {
      $aboutTextDiff = 'This page allows you to <b>update a selected entity type</b>.';
    }

    $aboutText = <<<TEXT
<p>$aboutTextDiff</p>
<p>
Please refer to the <a href='/admin/help/entity_update'>Help page</a>.
</p>
<p>
<b>CAUTION</b>
<ul>
  <li>Use this module only as a development helper, Do not use in production sites.</li>
  <li>The entity update may damage your database, therefore backup the database before taking any action.</li>
  <li>If you are using this system, you should be conscious of what you are doing.</li>
  <li> <b>You acknowledge that you are responsible for any issues due to this</b>.</li>
</ul>
</p>
<b>NOTE</b> : From here, You can update if:<br>
<ul>
  <li>Any update if the entity type has no data.</li>
  <li>Add new fields to entity type even with data.</li>
</ul>
<i>You can try "Run Full Update", but use the <b>drush</b> interface for safe execution and to have log messages. (Type : <code><b>drush help upe</b></code> for help.)</i><br>
<br>
</p>
TEXT;

    $form['messages']['about'] = [
      '#type' => 'markup',
      '#markup' => $aboutText,
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#default_value' => '0',
      '#title' => $this->t('Yes, I want to execute the following action.'),
    ];
    $form['action'] = [
      '#type' => 'hidden',
      '#default_value' => $action,
    ];

    // Cleanup archives.
    if ($action == 'clean') {
      // Run backuped entities cleanup.
      $form['submit_clean'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cleanup'),
        '#submit' => ['::submitFormClean'],
        '#description' => '',
      ];
    }
    // Create entities from entity backup database.
    elseif ($action == 'rescue') {
      $form['submit_rescue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Run Entity Rescue'),
        '#submit' => ['::submitFormRescue'],
      ];
    }
    // Selected Entity type update.
    elseif ($action == 'type') {
      $list = EntityUpdate::getEntityTypesToUpdate();
      if (!empty($list)) {
        $form['action_entitytype'] = [
          '#type' => 'details',
          '#title' => 'Update a selected entity type',
          '#open' => TRUE,
        ];
        $options = [];
        foreach ($list as $entity_type_id => $value) {
          $options[$entity_type_id] = $entity_type_id;
        }
        $form['action_entitytype']['entity_type_id'] = [
          '#type' => 'select',
          '#title' => $this->t('The entity type id to update'),
          '#options' => $options,
        ];
        // Update entity types with data. This action is not recomended from UI.
        $form['action_entitytype']['submit_type'] = [
          '#type' => 'submit',
          '#value' => $this->t('Run Type Update'),
          '#submit' => ['::submitFormSafe'],
          '#validate' => ['::validateForm', '::validateSafe'],
          '#description' => '',
        ];
      }
      else {
        $form['action_entitytype'] = [
          '#type' => 'markup',
          '#markup' => 'Nothing to update',
        ];
      }
    }
    // Default (Basic)
    else {
      $form['force'] = [
        '#type' => 'checkbox',
        '#default_value' => '0',
        '#title' => $this->t('Try force update.'),
        '#description' => $this->t('Run basic update using --force option.'),
      ];

      $form['submit_basic'] = [
        '#type' => 'submit',
        '#value' => $this->t('Run Basic Update'),
        '#submit' => ['::submitFormBasic'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $list = EntityUpdate::getEntityTypesToUpdate();
    $action = $form_state->getValue('action');
    $actions = ['rescue', 'clean'];
    if (!in_array($action, $actions) && empty($list)) {
      $form_state->setErrorByName('about', $this->t("Nothing to update. All entities are up to date."));
    }
    elseif (!$form_state->getValue('confirm')) {
      $form_state->setErrorByName('confirm', $this->t("If you want to execute, please check the checkbox."));
    }
  }

  /**
   * Safe mode validation.
   *
   * {@inheritdoc}
   */
  public function validateSafe(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getValue('entity_type_id');
    $list = EntityUpdate::getEntityTypesToUpdate($type);

    if (!isset($list[$type])) {
      $form_state->setErrorByName('entity_type_id', $this->t("No updates for entity_type_id"));
      return;
    }
    $entity_type_changes = $list[$type];

    // Init flags.
    $flg_has_install = FALSE;
    $flg_has_uninstall = FALSE;

    // Check install/uninstall.
    foreach ($entity_type_changes as $entity_change_summ) {
      if (strstr($entity_change_summ, "uninstalled")) {
        $flg_has_uninstall = TRUE;
      }
      else {
        $flg_has_install = TRUE;
      }
    }

    // Check and print instruction.
    if ($flg_has_install && $flg_has_uninstall) {
      $form_state->setErrorByName('entity_type_id', $this->t("Multiple actions detected, cant update if contains data. Use basic method."));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit actions.
  }

  /**
   * Run updates using basic method.
   */
  public function submitFormBasic(array &$form, FormStateInterface $form_state) {
    try {
      $force = $form_state->getValue('force');
      $res = EntityUpdate::basicUpdate($force);
      $res_str = $res ? 'SUCCESS' : 'FAIL';
      $status = $res ? 'status' : 'error';
      $this->messenger()->addMessage($this->t("Entity update @res", ['@res' => $res_str]), $status);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t("Entity update Fail"), 'error');
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Run updates using safe (full) method.
   */
  public function submitFormSafe(array &$form, FormStateInterface $form_state) {
    try {
      $type = $form_state->getValue('entity_type_id');
      $entity_type = entity_update_get_entity_type($type);
      // Update the entity type.
      $res = EntityUpdate::safeUpdateMain($entity_type);
      $res_str = $res ? 'SUCCESS' : 'FAIL';
      $status = $res ? 'status' : 'error';
      $options = [
        '@res' => $res_str,
        '@type' => $entity_type->getLabel(),
      ];
      $this->messenger()->addMessage($this->t("Entity @type update @res", $options), $status);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t("Entity update Fail"), 'error');
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Run backuped entities cleanup.
   */
  public function submitFormClean(array &$form, FormStateInterface $form_state) {
    try {
      $res = EntityUpdate::cleanupEntityBackup();
      $res_str = $res ? 'SUCCESS' : 'FAIL';
      $status = $res ? 'status' : 'error';
      $this->messenger()->addMessage($this->t("Backups cleanup @res", ['@res' => $res_str]), $status);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t("Entity update Fail"), 'error');
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Run rescue entities.
   */
  public function submitFormRescue(array &$form, FormStateInterface $form_state) {
    try {
      $res = EntityUpdate::entityUpdateDataRestore();
      $res_str = $res ? 'SUCCESS' : 'FAIL';
      $status = $res ? 'status' : 'error';
      $this->messenger()->addMessage($this->t("Entity rescue @res", ['@res' => $res_str]), $status);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t("Entity update Fail"), 'error');
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }
  }

}

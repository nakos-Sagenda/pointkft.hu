<?php

namespace Drupal\gdpr_compliance\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the form controller.
 */
class SettingsFormWarning extends ConfigFormBase {

  protected $moduleHandler;
  protected $entityTypeManager;
  protected $entityTypeBundle;
  protected $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * SettingsFormWarning constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeManager
   *   Entity Manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $moduleHandler,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfo $entityTypeBundle
  ) {
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundle = $entityTypeBundle;
    $this->entityTypes = [
      'contact_message' => [
        'name' => 'Contact',
        'module' => 'contact',
      ],
      'node' => [
        'name' => 'Node',
        'module' => 'node',
      ],
      'webform' => [
        'name' => 'Webform',
        'module' => 'webform',
      ],
    ];
    parent::__construct($config_factory);
  }

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
    $form['from-morelink'] = [
      '#title' => $this->t('Url for form [Link to site policy agreement].'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('from-morelink'),
      '#description' => $this->t('Relative path starts with "/", or absolute start with http/https.'),
    ];
    $form['user'] = [
      '#type' => 'details',
      '#title' => $this->t('@module GDPR form warning', ['@module' => 'user']),
      '#open' => TRUE,
    ];
    $form["user"]['user-register'] = [
      '#title' => $this->t('Enable on user registration'),
      '#description' => $this->t('Display alert on user-register form (/user/register).'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('user-register'),
    ];
    $form["user"]['user-login'] = [
      '#title' => $this->t('Enable on user login'),
      '#description' => $this->t('Display alert on user-login form (/user/login).'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('user-login'),
    ];
    foreach ($this->entityTypes as $enity_type => $enity_info) {
      $name = $enity_info['name'];
      $form[$enity_type] = [
        '#type' => 'details',
        '#title' => $this->t('@name GDPR form warning', ['@name' => $name]),
        '#open' => TRUE,
      ];
      if (!$this->moduleHandler->moduleExists($enity_info['module'])) {
        $form[$enity_type]['#open'] = FALSE;
        $form[$enity_type]["$enity_type-miss"] = [
          '#markup' => '<p>' . $this->t("Module '@module' not enabled.", ['@module' => $name]) . '</p>',
        ];
      }
      else {
        $form[$enity_type]["$enity_type-mode"] = [
          '#title' => $this->t("Display mode"),
          '#type' => 'radios',
          '#options' => [
            'disable' => 'Disable',
            'all' => 'All',
            'custom' => 'Custom bundles',
          ],
          '#default_value' => $config->get("$enity_type-mode"),
        ];
        $options = [];
        $bundles = $this->getBundles($enity_type);
        if (!empty($bundles)) {
          foreach ($bundles as $key => $value) {
            $options[$key] = $value['label'];
          }
          $form[$enity_type]["$enity_type-bundles"] = [
            '#title' => $this->t("@name bundles warning display on", ['@name' => $name]),
            '#type' => 'checkboxes',
            '#options' => $options,
          ];
          $default = $config->get("$enity_type-bundles");
          if (!empty($default)) {
            $form[$enity_type]["$enity_type-bundles"]['#default_value'] = $default;
          }
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements a form submit handler.
   */
  private function getBundles($enity_type) {
    if ($enity_type == 'webform') {
      $wforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();
      $bundles = [];
      foreach ($wforms as $wform) {
        $bundles[$wform->id()]['label'] = $wform->label();
      }
    }
    else {
      $bundles = $this->entityTypeBundle->getBundleInfo($enity_type);
    }
    return $bundles;
  }

  /**
   * Implements a form submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $config = $this->config('gdpr_compliance.settings');
    foreach ($this->entityTypes as $enity_type => $enity_info) {
      $config
        ->set("$enity_type-mode", $form_state->getValue("$enity_type-mode"))
        ->set("$enity_type-bundles", $form_state->getValue("$enity_type-bundles"));
    }
    $config
      ->set('from-morelink', $form_state->getValue('from-morelink'))
      ->set('user-login', $form_state->getValue('user-login'))
      ->set('user-register', $form_state->getValue('user-register'))
      ->save();
  }

}

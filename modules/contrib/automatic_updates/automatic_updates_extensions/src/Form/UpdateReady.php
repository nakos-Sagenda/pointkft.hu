<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates_extensions\Form;

use Drupal\automatic_updates\Form\UpdateFormBase;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\ValidationResult;
use Drupal\automatic_updates_extensions\BatchProcessor;
use Drupal\automatic_updates\BatchProcessor as AutoUpdatesBatchProcessor;
use Drupal\automatic_updates_extensions\ExtensionUpdater;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\Exception\StageOwnershipException;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a form to commit staged updates.
 *
 * @internal
 *   Form classes are internal.
 */
final class UpdateReady extends UpdateFormBase {

  /**
   * The updater service.
   *
   * @var \Drupal\automatic_updates_extensions\ExtensionUpdater
   */
  protected $updater;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The module list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new UpdateReady object.
   *
   * @param \Drupal\automatic_updates_extensions\ExtensionUpdater $updater
   *   The updater service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module list service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   */
  public function __construct(ExtensionUpdater $updater, MessengerInterface $messenger, StateInterface $state, ModuleExtensionList $module_list, RendererInterface $renderer, EventDispatcherInterface $event_dispatcher) {
    $this->updater = $updater;
    $this->setMessenger($messenger);
    $this->state = $state;
    $this->moduleList = $module_list;
    $this->renderer = $renderer;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'automatic_updates_update_ready_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('automatic_updates_extensions.updater'),
      $container->get('messenger'),
      $container->get('state'),
      $container->get('extension.list.module'),
      $container->get('renderer'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $stage_id = NULL) {
    try {
      $this->updater->claim($stage_id);
    }
    catch (StageOwnershipException $e) {
      $this->messenger()->addError($this->t('Cannot continue the update because another Composer operation is currently in progress.'));
      return $form;
    }
    catch (ApplyFailedException $e) {
      $this->messenger()->addError($e->getMessage());
      return $form;
    }

    $messages = [];

    // Don't set any messages if the form has been submitted, because we don't
    // want them to be set during form submit.
    if (!$form_state->getUserInput()) {
      foreach ($messages as $type => $messages_of_type) {
        foreach ($messages_of_type as $message) {
          $this->messenger()->addMessage($message, $type);
        }
      }
    }

    $form['actions'] = [
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel update'),
        '#submit' => ['::cancel'],
      ],
      '#type' => 'actions',
    ];
    $form['stage_id'] = [
      '#type' => 'value',
      '#value' => $stage_id,
    ];
    $form['package_updates'] = $this->showUpdates();
    $form['backup'] = [
      '#prefix' => '<strong>',
      '#markup' => $this->t('Back up your database and site before you continue. <a href=":backup_url">Learn how</a>.', [':backup_url' => 'https://www.drupal.org/node/22281']),
      '#suffix' => '</strong>',
    ];
    $form['maintenance_mode'] = [
      '#title' => $this->t('Perform updates with site in maintenance mode (strongly recommended)'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];

    // Don't run the status checks once the form has been submitted.
    if (!$form_state->getUserInput()) {
      $results = $this->runStatusCheck($this->updater, $this->eventDispatcher);
      // This will have no effect if $results is empty.
      $this->displayResults($results, $this->renderer);
      // If any errors occurred, return the form early so the user cannot
      // continue.
      if (ValidationResult::getOverallSeverity($results) === SystemManager::REQUIREMENT_ERROR) {
        return $form;
      }
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Store maintenance_mode setting so we can restore it when done.
    $this->getRequest()
      ->getSession()
      ->set(AutoUpdatesBatchProcessor::MAINTENANCE_MODE_SESSION_KEY, $this->state->get('system.maintenance_mode'));

    if ($form_state->getValue('maintenance_mode')) {
      $this->state->set('system.maintenance_mode', TRUE);
    }
    $stage_id = $form_state->getValue('stage_id');
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Apply updates'))
      ->setInitMessage($this->t('Preparing to apply updates'))
      ->addOperation([BatchProcessor::class, 'commit'], [$stage_id])
      ->addOperation([BatchProcessor::class, 'postApply'], [$stage_id])
      ->addOperation([BatchProcessor::class, 'clean'], [$stage_id])
      ->setFinishCallback([BatchProcessor::class, 'finishCommit'])
      ->toArray();

    batch_set($batch);
  }

  /**
   * Cancels the in-progress update.
   */
  public function cancel(array &$form, FormStateInterface $form_state): void {
    try {
      $this->updater->destroy();
      $this->messenger()->addStatus($this->t('The update was successfully cancelled.'));
      $form_state->setRedirect('automatic_updates_extensions.report_update');
    }
    catch (StageException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Displays all projects that will be updated.
   *
   * @return mixed[][]
   *   A render array displaying packages that will be updated.
   */
  private function showUpdates(): array {
    // Get packages that were updated in the stage directory.
    $active = $this->updater->getActiveComposer();
    $staged = $this->updater->getStageComposer();
    $updated_packages = $staged->getPackagesWithDifferentVersionsIn($active);

    // Build a list of package names that were updated by user request.
    $updated_by_request = [];
    foreach ($this->updater->getPackageVersions() as $group) {
      $updated_by_request = array_merge($updated_by_request, array_keys($group));
    }

    $installed_packages = $active->getInstalledPackages();
    $updated_by_request_info = [];
    $updated_project_info = [];
    $supported_package_types = ['drupal-module', 'drupal-theme'];

    // Compile an array of relevant information about the packages that will be
    // updated.
    foreach ($updated_packages as $name => $updated_package) {
      // Ignore anything that isn't a module or a theme.
      if (!in_array($updated_package->getType(), $supported_package_types, TRUE)) {
        continue;
      }
      $updated_project_info[$name] = [
        'title' => $this->getProjectTitle($updated_package->getName()),
        'installed_version' => $installed_packages[$name]->getPrettyVersion(),
        'updated_version' => $updated_package->getPrettyVersion(),
      ];
    }

    foreach (array_keys($updated_packages) as $name) {
      // Sort the updated packages into two groups: the ones that were updated
      // at the request of the user, and the ones that got updated anyway
      // (probably due to Composer's dependency resolution).
      if (in_array($name, $updated_by_request, TRUE)) {
        $updated_by_request_info[$name] = $updated_project_info[$name];
        unset($updated_project_info[$name]);
      }
    }
    $output = [];
    if ($updated_by_request_info) {
      // Create the list of messages for the packages updated by request.
      $output['requested'] = $this->getUpdatedPackagesItemList($updated_by_request_info, $this->t('The following projects will be updated:'));
    }

    if ($updated_project_info) {
      // Create the list of messages for packages that were updated
      // incidentally.
      $output['dependencies'] = $this->getUpdatedPackagesItemList($updated_project_info, $this->t('The following dependencies will also be updated:'));
    }
    return $output;
  }

  /**
   * Gets the human-readable project title for a Composer package.
   *
   * @param string $package_name
   *   Package name.
   *
   * @return string
   *   The human-readable title of the project.
   */
  private function getProjectTitle(string $package_name): string {
    $project_name = str_replace('drupal/', '', $package_name);
    $project_info = new ProjectInfo($project_name);
    $project_data = $project_info->getProjectInfo();
    if ($project_data) {
      return $project_data['title'];
    }
    else {
      return $project_name;
    }
  }

  /**
   * Generates an item list of packages that will be updated.
   *
   * @param array[] $updated_packages
   *   An array of packages that will be updated, each sub-array containing the
   *   project title, installed version, and target version.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $item_list_title
   *   The title of the generated item list.
   *
   * @return array
   *   A render array for the generated item list.
   */
  private function getUpdatedPackagesItemList(array $updated_packages, TranslatableMarkup $item_list_title): array {
    $create_message_for_project = function (array $project): TranslatableMarkup {
      return $this->t('@title from @from_version to @to_version', [
        '@title' => $project['title'],
        '@from_version' => $project['installed_version'],
        '@to_version' => $project['updated_version'],
      ]);
    };
    return [
      '#theme' => 'item_list',
      '#prefix' => '<p>' . $item_list_title . '</p>',
      '#items' => array_map($create_message_for_project, $updated_packages),
    ];
  }

}

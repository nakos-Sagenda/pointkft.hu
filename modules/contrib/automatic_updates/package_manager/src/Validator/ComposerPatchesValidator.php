<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\package_manager\ComposerUtility;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates the configuration of the cweagans/composer-patches plugin.
 *
 * To ensure that applied patches remain consistent between the active and
 * stage directories, the following rules are enforced if the patcher is
 * installed:
 * - It must be installed in both places, or in neither of them. It can't, for
 *   example, be installed in the active directory but not the stage directory
 *   (or vice-versa).
 * - It must be one of the project's direct runtime or dev dependencies.
 * - It cannot be installed or removed by Package Manager. In other words, it
 *   must be added to the project at the command line by someone technical
 *   enough to install and configure it properly.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ComposerPatchesValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The name of the plugin being analyzed.
   *
   * @var string
   */
  private const PLUGIN_NAME = 'cweagans/composer-patches';

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a ComposerPatchesValidator object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Validates the status of the patcher plugin.
   *
   * @param \Drupal\package_manager\Event\PreOperationStageEvent $event
   *   The event object.
   */
  public function validatePatcher(PreOperationStageEvent $event): void {
    $messages = [];

    $stage = $event->getStage();
    [$plugin_installed_in_active, $is_active_root_requirement, $active_configuration_ok] = $this->computePatcherStatus($stage->getActiveComposer());
    try {
      [$plugin_installed_in_stage, $is_stage_root_requirement, $stage_configuration_ok] = $this->computePatcherStatus($stage->getStageComposer());
      $has_staged_update = TRUE;
    }
    catch (\LogicException $e) {
      // No staged update exists.
      $has_staged_update = FALSE;
    }

    // If there's a staged update and the patcher has been installed or removed
    // in the stage directory, that's a problem.
    if ($has_staged_update && $plugin_installed_in_active !== $plugin_installed_in_stage) {
      if ($plugin_installed_in_stage) {
        $message = $this->t('It cannot be installed by Package Manager.');
      }
      else {
        $message = $this->t('It cannot be removed by Package Manager.');
      }
      $messages[] = $this->createErrorMessage($message, 'package-manager-faq-composer-patches-installed-or-removed');
    }

    // If the patcher is not listed in the runtime or dev dependencies, that's
    // an error as well.
    if (($plugin_installed_in_active && !$is_active_root_requirement) || ($has_staged_update && $plugin_installed_in_stage && !$is_stage_root_requirement)) {
      $messages[] = $this->createErrorMessage($this->t('It must be a root dependency.'), 'package-manager-faq-composer-patches-not-a-root-dependency');
    }

    // If the plugin is misconfigured in either the active or stage directories,
    // flag an error.
    if (($plugin_installed_in_active && !$active_configuration_ok) || ($has_staged_update && $plugin_installed_in_stage && !$stage_configuration_ok)) {
      $messages[] = $this->t('The <code>composer-exit-on-patch-failure</code> key is not set to <code>true</code> in the <code>extra</code> section of <code>composer.json</code>.');
    }

    if ($messages) {
      $summary = $this->t("Problems detected related to the Composer plugin <code>@plugin</code>.", [
        '@plugin' => static::PLUGIN_NAME,
      ]);
      $event->addError($messages, $summary);
    }
  }

  /**
   * Appends a link to online help to an error message.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The error message.
   * @param string $fragment
   *   The fragment of the online help to link to.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The final, translated error message.
   */
  private function createErrorMessage(TranslatableMarkup $message, string $fragment): TranslatableMarkup {
    if ($this->moduleHandler->moduleExists('help')) {
      $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
        ->setOption('fragment', $fragment)
        ->toString();

      return $this->t('@message See <a href=":url">the help page</a> for information on how to resolve the problem.', [
        '@message' => $message,
        ':url' => $url,
      ]);
    }
    return $message;
  }

  /**
   * Computes the status of the patcher plugin in a particular directory.
   *
   * @param \Drupal\package_manager\ComposerUtility $composer
   *   A Composer utility for a specific directory.
   *
   * @return bool[]
   *   An indexed array containing three booleans, in order:
   *   - Whether the patcher plugin is installed.
   *   - Whether the patcher plugin is a root requirement in composer.json (in
   *     either the runtime or dev dependencies).
   *   - Whether the `composer-exit-on-patch-failure` flag is set in the `extra`
   *     section of composer.json.
   */
  private function computePatcherStatus(ComposerUtility $composer): array {
    $is_installed = array_key_exists(static::PLUGIN_NAME, $composer->getInstalledPackages());

    $root_package = $composer->getComposer()->getPackage();
    $is_root_requirement = array_key_exists(static::PLUGIN_NAME, $root_package->getRequires()) || array_key_exists(static::PLUGIN_NAME, $root_package->getDevRequires());

    $extra = $root_package->getExtra();
    $exit_on_failure = !empty($extra['composer-exit-on-patch-failure']);

    return [$is_installed, $is_root_requirement, $exit_on_failure];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'validatePatcher',
      PreApplyEvent::class => 'validatePatcher',
      StatusCheckEvent::class => 'validatePatcher',
    ];
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Composer\Package\PackageInterface;
use Drupal\automatic_updates\Updater;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates the staged Drupal projects.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class StagedProjectsValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a StagedProjectsValidation object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   */
  public function __construct(TranslationInterface $translation) {
    $this->setStringTranslation($translation);
  }

  /**
   * Validates the staged packages.
   *
   * @param \Drupal\package_manager\Event\PreApplyEvent $event
   *   The event object.
   */
  public function validateStagedProjects(PreApplyEvent $event): void {
    $stage = $event->getStage();
    // We only want to do this check if the stage belongs to Automatic Updates.
    if (!$stage instanceof Updater) {
      return;
    }

    try {
      $active = $stage->getActiveComposer();
      $stage = $stage->getStageComposer();
    }
    catch (\Throwable $e) {
      $event->addErrorFromThrowable($e);
      return;
    }

    $type_map = [
      'drupal-module' => $this->t('module'),
      'drupal-custom-module' => $this->t('custom module'),
      'drupal-theme' => $this->t('theme'),
      'drupal-custom-theme' => $this->t('custom theme'),
    ];
    $filter = function (PackageInterface $package) use ($type_map): bool {
      return array_key_exists($package->getType(), $type_map);
    };
    $new_packages = $stage->getPackagesNotIn($active);
    $removed_packages = $active->getPackagesNotIn($stage);
    $updated_packages = $active->getPackagesWithDifferentVersionsIn($stage);

    // Check if any new Drupal projects were installed.
    if ($new_packages = array_filter($new_packages, $filter)) {
      $new_packages_messages = [];

      foreach ($new_packages as $new_package) {
        $new_packages_messages[] = $this->t(
          "@type '@name' installed.",
          [
            '@type' => $type_map[$new_package->getType()],
            '@name' => $new_package->getName(),
          ]
        );
      }
      $new_packages_summary = $this->formatPlural(
        count($new_packages_messages),
        'The update cannot proceed because the following Drupal project was installed during the update.',
        'The update cannot proceed because the following Drupal projects were installed during the update.'
      );
      $event->addError($new_packages_messages, $new_packages_summary);
    }

    // Check if any Drupal projects were removed.
    if ($removed_packages = array_filter($removed_packages, $filter)) {
      $removed_packages_messages = [];
      foreach ($removed_packages as $removed_package) {
        $removed_packages_messages[] = $this->t(
          "@type '@name' removed.",
          [
            '@type' => $type_map[$removed_package->getType()],
            '@name' => $removed_package->getName(),
          ]
        );
      }
      $removed_packages_summary = $this->formatPlural(
        count($removed_packages_messages),
        'The update cannot proceed because the following Drupal project was removed during the update.',
        'The update cannot proceed because the following Drupal projects were removed during the update.'
      );
      $event->addError($removed_packages_messages, $removed_packages_summary);
    }

    // Check if any Drupal projects were neither installed or removed, but had
    // their version numbers changed.
    if ($updated_packages = array_filter($updated_packages, $filter)) {
      $staged_packages = $stage->getInstalledPackages();

      foreach ($updated_packages as $name => $updated_package) {
        $version_change_messages[] = $this->t(
          "@type '@name' from @active_version to @staged_version.",
          [
            '@type' => $type_map[$updated_package->getType()],
            '@name' => $updated_package->getName(),
            '@staged_version' => $staged_packages[$name]->getPrettyVersion(),
            '@active_version' => $updated_package->getPrettyVersion(),
          ]
        );
      }
      if (!empty($version_change_messages)) {
        $version_change_summary = $this->formatPlural(
          count($version_change_messages),
          'The update cannot proceed because the following Drupal project was unexpectedly updated. Only Drupal Core updates are currently supported.',
          'The update cannot proceed because the following Drupal projects were unexpectedly updated. Only Drupal Core updates are currently supported.'
        );
        $event->addError($version_change_messages, $version_change_summary);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PreApplyEvent::class][] = ['validateStagedProjects'];
    return $events;
  }

}

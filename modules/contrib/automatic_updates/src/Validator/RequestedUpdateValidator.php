<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Composer\Semver\Semver;
use Drupal\automatic_updates\Updater;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\package_manager\Event\PreApplyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that requested packages have been updated.
 */
class RequestedUpdateValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a RequestedUpdateValidator object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   */
  public function __construct(TranslationInterface $translation) {
    $this->setStringTranslation($translation);
  }

  /**
   * Validates that requested packages have been updated to the right version.
   *
   * @param \Drupal\package_manager\Event\PreApplyEvent $event
   *   The pre-apply event.
   */
  public function checkRequestedStagedVersion(PreApplyEvent $event): void {
    $stage = $event->getStage();
    if (!($stage instanceof Updater)) {
      return;
    }
    $requested_package_versions = $stage->getPackageVersions();
    $changed_stage_packages = $stage->getStageComposer()->getPackagesWithDifferentVersionsIn($stage->getActiveComposer());
    if (empty($changed_stage_packages)) {
      $event->addError([$this->t('No updates detected in the staging area.')]);
      return;
    }

    // Check for all changed the packages if they are updated to the requested
    // version.
    foreach (['production', 'dev'] as $package_type) {
      foreach ($requested_package_versions[$package_type] as $requested_package_name => $requested_version) {
        if (array_key_exists($requested_package_name, $changed_stage_packages)) {
          $staged_version = $changed_stage_packages[$requested_package_name]->getPrettyVersion();
          if (!Semver::satisfies($staged_version, $requested_version)) {
            $event->addError([
              $this->t(
                "The requested update to '@requested_package_name' to version '@requested_version' does not match the actual staged update to '@staged_version'.",
                [
                  '@requested_package_name' => $requested_package_name,
                  '@requested_version' => $requested_version,
                  '@staged_version' => $staged_version,
                ]
              ),
            ]);
          }
        }
        else {
          $event->addError([
            $this->t(
              "The requested update to '@requested_package_name' to version '@requested_version' was not performed.",
              [
                '@requested_package_name' => $requested_package_name,
                '@requested_version' => $requested_version,
              ]
            ),
          ]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PreApplyEvent::class][] = ['checkRequestedStagedVersion'];
    return $events;
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that newly installed packages don't overwrite existing directories.
 *
 * Whether a new package in the stage directory would overwrite an existing
 * directory in the active directory when the operation is applied is determined
 * by inspecting the `install_path` property of the staged package.
 *
 * Not all packages will have an `install_path` property and therefore
 * those packages without this property will be ignored by this validator. For
 * instance packages with the `metapackage` type do not have this property as
 * they contain no files that are written to the file system. There are also may
 * be other custom types that do not have an `install_path` property.
 *
 * The Composer facade at https://packages.drupal.org/8 currently uses the
 * `metapackage` type for submodules of Drupal projects.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 *
 * @link https://getcomposer.org/doc/04-schema.md#type
 *
 * @see \Drupal\package_manager\ComposerUtility::getInstalledPackagesData()
 */
final class OverwriteExistingPackagesValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a OverwriteExistingPackagesValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * Validates that new installed packages don't overwrite existing directories.
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    $stage = $event->getStage();
    $active_composer = $stage->getActiveComposer();
    $stage_composer = $stage->getStageComposer();
    $active_dir = $this->pathLocator->getProjectRoot();
    $stage_dir = $stage->getStageDirectory();
    $new_packages = $stage_composer->getPackagesNotIn($active_composer);
    $installed_packages_data = $stage_composer->getInstalledPackagesData();

    // Although unlikely, it is possible that package data could be missing for
    // some new packages.
    $missing_new_packages = array_diff_key($new_packages, $installed_packages_data);
    if ($missing_new_packages) {
      $missing_new_packages = array_keys($missing_new_packages);
      foreach ($missing_new_packages as &$missing_new_package) {
        $missing_new_package = $this->t('@missing_new_package', ['@missing_new_package' => $missing_new_package]);
      }
      $event->addError(array_values($missing_new_packages), $this->t('Package Manager could not get the data for the following packages.'));
      return;
    }

    $new_installed_data = array_intersect_key($installed_packages_data, $new_packages);
    foreach ($new_installed_data as $package_name => $data) {
      if (empty($data['install_path'])) {
        // Packages without an `install_path` cannot overwrite existing
        // directories.
        continue;
      }
      $relative_path = str_replace($stage_dir, '', $data['install_path']);
      if (is_dir($active_dir . DIRECTORY_SEPARATOR . $relative_path)) {
        $event->addError([
          $this->t('The new package @package will be installed in the directory @path, which already exists but is not managed by Composer.', [
            '@package' => $package_name,
            '@path' => $relative_path,
          ]),
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreApplyEvent::class => 'validateStagePreOperation',
    ];
  }

}

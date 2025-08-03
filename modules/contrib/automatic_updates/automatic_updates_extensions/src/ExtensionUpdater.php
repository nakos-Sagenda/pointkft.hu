<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates_extensions;

use Drupal\automatic_updates\Exception\UpdateException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\FailureMarker;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\Event\StageEvent;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\Stage;
use Drupal\package_manager\UnusedConfigFactory;
use PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface;
use PhpTuf\ComposerStager\Domain\Core\Committer\CommitterInterface;
use PhpTuf\ComposerStager\Domain\Core\Stager\StagerInterface;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service to perform updates for modules and themes.
 *
 * @internal
 *   This class is an internal part of the module's update handling and
 *   should not be used by external code.
 */
class ExtensionUpdater extends Stage {

  /**
   * {@inheritdoc}
   *
   * @todo Remove this in https://www.drupal.org/i/3303167
   */
  public function __construct(ConfigFactoryInterface $config_factory, PathLocator $path_locator, BeginnerInterface $beginner, StagerInterface $stager, CommitterInterface $committer, FileSystemInterface $file_system, EventDispatcherInterface $event_dispatcher, SharedTempStoreFactory $temp_store_factory, TimeInterface $time, PathFactoryInterface $path_factory = NULL, FailureMarker $failure_marker = NULL) {
    parent::__construct(new UnusedConfigFactory(), $path_locator, $beginner, $stager, $committer, $file_system, $event_dispatcher, $temp_store_factory, $time, $path_factory, $failure_marker);
  }

  /**
   * Begins the update.
   *
   * @param string[] $project_versions
   *   The versions of the packages to update to, keyed by package name.
   *
   * @return string
   *   The unique ID of the stage.
   *
   * @throws \InvalidArgumentException
   *   Thrown if no project version is provided.
   */
  public function begin(array $project_versions): string {
    if (empty($project_versions)) {
      throw new \InvalidArgumentException("No projects to begin the update");
    }
    $composer = $this->getActiveComposer();
    $package_versions = [
      'production' => [],
      'dev' => [],
    ];

    $require_dev = $composer->getComposer()
      ->getPackage()
      ->getDevRequires();
    $installed_packages = $composer->getInstalledPackages();
    foreach ($project_versions as $project_name => $version) {
      $package = $composer->getPackageForProject($project_name);
      if (empty($package)) {
        throw new \InvalidArgumentException("The project $project_name is not a Drupal project known to Composer and cannot be updated.");
      }

      // We don't support updating install profiles.
      if ($installed_packages[$package]->getType() === 'drupal-profile') {
        throw new \InvalidArgumentException("The project $project_name cannot be updated because updating install profiles is not supported.");
      }

      $group = array_key_exists($package, $require_dev) ? 'dev' : 'production';
      $package_versions[$group][$package] = LegacyVersionUtility::convertToSemanticVersion($version);
    }

    // Ensure that package versions are available to pre-create event
    // subscribers. We can't use ::setMetadata() here because it requires the
    // stage to be claimed, but that only happens during ::create().
    $this->tempStore->set(static::TEMPSTORE_METADATA_KEY, [
      'packages' => $package_versions,
    ]);
    return $this->create();
  }

  /**
   * Returns the package versions that will be required during the update.
   *
   * @return string[][]
   *   An array with two sub-arrays: 'production' and 'dev'. Each is a set of
   *   package versions, where the keys are package names and the values are
   *   version constraints understood by Composer.
   */
  public function getPackageVersions(): array {
    return $this->getMetadata('packages');
  }

  /**
   * Stages the update.
   */
  public function stage(): void {
    $this->checkOwnership();

    // Convert an associative array of package versions, keyed by name, to
    // command-line arguments in the form `vendor/name:version`.
    $map = function (array $versions): array {
      $requirements = [];
      foreach ($versions as $package => $version) {
        $requirements[] = "$package:$version";
      }
      return $requirements;
    };
    $versions = array_map($map, $this->getPackageVersions());
    $this->require($versions['production'], $versions['dev']);
  }

  /**
   * {@inheritdoc}
   */
  protected function dispatch(StageEvent $event, callable $on_error = NULL): void {
    try {
      parent::dispatch($event, $on_error);
    }
    catch (StageValidationException $e) {
      throw new UpdateException($e->getResults(), $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function apply(?int $timeout = 600): void {
    try {
      parent::apply($timeout);
    }
    catch (ApplyFailedException $exception) {
      throw new UpdateException([], 'The update operation failed to apply. The update may have been partially applied. It is recommended that the site be restored from a code backup.', $exception->getCode(), $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFailureMarkerMessage(): TranslatableMarkup {
    return $this->t('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
  }

}

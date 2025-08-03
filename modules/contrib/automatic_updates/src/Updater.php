<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates;

use Drupal\automatic_updates\Exception\UpdateException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\package_manager\Event\StageEvent;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\Exception\StageValidationException;
use Drupal\package_manager\FailureMarker;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\Stage;
use Drupal\package_manager\UnusedConfigFactory;
use PhpTuf\ComposerStager\Domain\Core\Beginner\BeginnerInterface;
use PhpTuf\ComposerStager\Domain\Core\Committer\CommitterInterface;
use PhpTuf\ComposerStager\Domain\Core\Stager\StagerInterface;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service to perform updates.
 *
 * Currently, only updates to Drupal core are supported. This is done by
 * changing the constraint for either 'drupal/core' or 'drupal/core-recommended'
 * in the project-level composer.json. If neither package is directly required
 * in the project-level composer.json, a requirement will be added.
 */
class Updater extends Stage {

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
   * @param int|null $timeout
   *   (optional) How long to allow the file copying operation to run before
   *   timing out, in seconds, or NULL to never time out. Defaults to 300
   *   seconds.
   *
   * @return string
   *   The unique ID of the stage.
   *
   * @throws \InvalidArgumentException
   *   Thrown if no project version for Drupal core is provided.
   */
  public function begin(array $project_versions, ?int $timeout = 300): string {
    if (count($project_versions) !== 1 || !array_key_exists('drupal', $project_versions)) {
      throw new \InvalidArgumentException("Currently only updates to Drupal core are supported.");
    }

    $composer = $this->getActiveComposer();
    $package_versions = [
      'production' => [],
      'dev' => [],
    ];

    $require_dev = $composer->getComposer()
      ->getPackage()
      ->getDevRequires();
    foreach (array_keys($composer->getCorePackages()) as $package) {
      $group = array_key_exists($package, $require_dev) ? 'dev' : 'production';
      $package_versions[$group][$package] = $project_versions['drupal'];
    }

    // Ensure that package versions are available to pre-create event
    // subscribers. We can't use ::setMetadata() here because it requires the
    // stage to be claimed, but that only happens during ::create().
    $this->tempStore->set(static::TEMPSTORE_METADATA_KEY, [
      'packages' => $package_versions,
    ]);
    return $this->create($timeout);
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
  public function stage(?int $timeout = 300): void {
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
    $this->require($versions['production'], $versions['dev'], $timeout);
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
      throw new UpdateException([], "The update operation failed to apply completely. All the files necessary to run Drupal correctly and securely are probably not present. It is strongly recommended to restore your site's code and database from a backup.", $exception->getCode(), $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFailureMarkerMessage(): TranslatableMarkup {
    return $this->t('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
  }

}

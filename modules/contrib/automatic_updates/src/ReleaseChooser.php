<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates;

use Composer\Semver\Semver;
use Drupal\automatic_updates\Validator\VersionPolicyValidator;
use Drupal\package_manager\ProjectInfo;
use Drupal\update\ProjectRelease;
use Drupal\Core\Extension\ExtensionVersion;

/**
 * Defines a class to choose a release of Drupal core to update to.
 */
final class ReleaseChooser {

  use VersionParsingTrait;

  /**
   * The version policy validator service.
   *
   * @var \Drupal\automatic_updates\Validator\VersionPolicyValidator
   */
  protected $versionPolicyValidator;

  /**
   * The project information fetcher.
   *
   * @var \Drupal\package_manager\ProjectInfo
   */
  protected $projectInfo;

  /**
   * Constructs an ReleaseChooser object.
   *
   * @param \Drupal\automatic_updates\Validator\VersionPolicyValidator $version_policy_validator
   *   The version validator.
   */
  public function __construct(VersionPolicyValidator $version_policy_validator) {
    $this->versionPolicyValidator = $version_policy_validator;
    $this->projectInfo = new ProjectInfo('drupal');
  }

  /**
   * Returns the releases that are installable.
   *
   * @param \Drupal\automatic_updates\Updater $updater
   *   The updater that will be used to install the releases.
   *
   * @return \Drupal\update\ProjectRelease[]
   *   The releases that are installable by the given updater, according to the
   *   version validator service.
   */
  protected function getInstallableReleases(Updater $updater): array {
    $filter = function (string $version) use ($updater): bool {
      return empty($this->versionPolicyValidator->validateVersion($updater, $version));
    };
    return array_filter(
      $this->projectInfo->getInstallableReleases(),
      $filter,
      ARRAY_FILTER_USE_KEY
    );
  }

  /**
   * Gets the most recent release in the same minor as a specified version.
   *
   * @param \Drupal\automatic_updates\Updater $updater
   *   The updater that will be used to install the release.
   * @param string $version
   *   The full semantic version number, which must include a patch version.
   *
   * @return \Drupal\update\ProjectRelease|null
   *   The most recent release in the minor if available, otherwise NULL.
   *
   * @throws \InvalidArgumentException
   *   If the given semantic version number does not contain a patch version.
   */
  public function getMostRecentReleaseInMinor(Updater $updater, string $version): ?ProjectRelease {
    if (static::getPatchVersion($version) === NULL) {
      throw new \InvalidArgumentException("The version number $version does not contain a patch version");
    }
    $releases = $this->getInstallableReleases($updater);
    foreach ($releases as $release) {
      // Checks if the release is in the same minor as the currently installed
      // version. For example, if the current version is 9.8.0 then the
      // constraint ~9.8.0 (equivalent to >=9.8.0 && <9.9.0) will be used to
      // check if the release is in the same minor.
      if (Semver::satisfies($release->getVersion(), "~$version")) {
        return $release;
      }
    }
    return NULL;
  }

  /**
   * Gets the installed version of Drupal core.
   *
   * @return string
   *   The installed version of Drupal core.
   */
  protected function getInstalledVersion(): string {
    return $this->projectInfo->getInstalledVersion();
  }

  /**
   * Gets the latest release in the currently installed minor.
   *
   * This will only return a release if it passes the ::isValidVersion() method
   * of the version validator service injected into this class.
   *
   * @param \Drupal\automatic_updates\Updater $updater
   *   The updater which will install the release.
   *
   * @return \Drupal\update\ProjectRelease|null
   *   The latest release in the currently installed minor, if any, otherwise
   *   NULL.
   */
  public function getLatestInInstalledMinor(Updater $updater): ?ProjectRelease {
    return $this->getMostRecentReleaseInMinor($updater, $this->getInstalledVersion());
  }

  /**
   * Gets the latest release in the next minor.
   *
   * This will only return a release if it passes the ::isValidVersion() method
   * of the version validator service injected into this class.
   *
   * @param \Drupal\automatic_updates\Updater $updater
   *   The updater which will install the release.
   *
   * @return \Drupal\update\ProjectRelease|null
   *   The latest release in the next minor, if any, otherwise NULL.
   */
  public function getLatestInNextMinor(Updater $updater): ?ProjectRelease {
    $installed_version = ExtensionVersion::createFromVersionString($this->getInstalledVersion());
    $next_minor = $installed_version->getMajorVersion() . '.' . (((int) $installed_version->getMinorVersion()) + 1) . '.0';
    return $this->getMostRecentReleaseInMinor($updater, $next_minor);
  }

}

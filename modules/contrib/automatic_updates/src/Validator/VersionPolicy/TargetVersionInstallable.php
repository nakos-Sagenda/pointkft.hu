<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule requiring the target version to be an installable release.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class TargetVersionInstallable {

  use StringTranslationTrait;

  /**
   * Checks that the target version of Drupal is a known installable release.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal, or NULL if not known.
   * @param \Drupal\update\ProjectRelease[] $available_releases
   *   The available releases of Drupal core.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version, ?string $target_version, array $available_releases): array {
    // If the target version isn't in the list of installable releases, we
    // should flag an error.
    if (empty($available_releases) || !array_key_exists($target_version, $available_releases)) {
      return [
        $this->t('Cannot update Drupal core to @target_version because it is not in the list of installable releases.', [
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\automatic_updates\VersionParsingTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A policy rule that allows minor updates if enabled in configuration.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class MinorUpdatesEnabled implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use VersionParsingTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a MinorUpdatesEnabled object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Checks that the target minor version of Drupal can be updated to.
   *
   * The update will only be allowed if the allow_core_minor_updates flag is
   * set to TRUE in config.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal, or NULL if not known.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version, ?string $target_version): array {
    $installed_minor = static::getMajorAndMinorVersion($installed_version);
    $target_minor = static::getMajorAndMinorVersion($target_version);

    if ($installed_minor === $target_minor) {
      return [];
    }

    $minor_updates_allowed = $this->configFactory->get('automatic_updates.settings')
      ->get('allow_core_minor_updates');

    if (!$minor_updates_allowed) {
      return [
        $this->t('Drupal cannot be automatically updated from @installed_version to @target_version because automatic updates from one minor version to another are not supported.', [
          '@installed_version' => $installed_version,
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}

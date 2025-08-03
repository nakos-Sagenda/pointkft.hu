<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Composer\Package\Package;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathExcluder\VendorHardeningExcluder;
use Drupal\package_manager\PathLocator;
use PhpTuf\ComposerStager\Domain\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates the allowed composer plugins, both in active and stage.
 *
 * Composer plugins can make far-reaching changes on the filesystem. That is why
 * they can cause Package Manager (more specifically the infrastructure it uses:
 * php-tuf/composer-stager) to not work reliably; potentially even break a site!
 *
 * This validator restricts the use of composer plugins:
 * - using arbitrary composer plugins is discouraged by composer, but disallowed
 *   by this module (it is too risky):
 *   @code config.allowed-plugins = true @endcode is forbidden.
 * - installed composer plugins that are not allowed (in composer.json's
 *   @code config.allowed-plugins @endcode) are not executed by composer, so
 *   these are safe
 * - installed composer plugins that are allowed need to be either explicitly
 *   supported by this validator (and they may need their own validation to
 *   ensure their configuration is safe, for example Drupal core's vendor
 *   hardening plugin), or explicitly trusted, by adding it to the
 *   @code package_manager.settings @endcode configuration's
 *   @code additional_trusted_composer_plugins @endcode list.
 *
 * @todo Determine how other Composer plugins will be supported in
 *    https://drupal.org/i/3339417.
 *
 * @see https://getcomposer.org/doc/04-schema.md#type
 * @see https://getcomposer.org/doc/articles/plugins.md
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ComposerPluginsValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Composer plugins known to modify other packages, but that are validated.
   *
   * (The validation guarantees they are safe to use.)
   *
   * Keys are composer plugin package names, values are associated validators or
   * excluders necessary to make those composer plugins work reliably with the
   * Package Manager module.
   *
   * @var string[][]
   */
  private const SUPPORTED_PLUGINS_THAT_DO_MODIFY = [
    // cSpell:disable
    'cweagans/composer-patches' => ComposerPatchesValidator::class,
    'drupal/core-vendor-hardening' => VendorHardeningExcluder::class,
    // cSpell:enable
  ];

  /**
   * The composer plugins are known not to modify other packages.
   *
   * @var string[]
   */
  private const SUPPORTED_PLUGINS_THAT_DO_NOT_MODIFY = [
    // cSpell:disable
    'composer/installers',
    'dealerdirect/phpcodesniffer-composer-installer',
    'drupal/core-composer-scaffold',
    'drupal/core-project-message',
    'phpstan/extension-installer',
    // cSpell:enable
  ];

  /**
   * The additional trusted composer plugin package names.
   *
   * Note: these are normalized package names.
   *
   * @var string[]
   * @see \Composer\Package\PackageInterface::getName()
   * @see \Composer\Package\PackageInterface::getPrettyName()
   */
  protected array $additionalTrustedComposerPlugins;

  /**
   * The Composer inspector service.
   *
   * @var \Drupal\package_manager\ComposerInspector
   */
  protected ComposerInspector $inspector;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected PathLocator $pathLocator;

  /**
   * Constructs a new ComposerPluginsValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\package_manager\ComposerInspector $inspector
   *   The Composer inspector service.
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ComposerInspector $inspector, PathLocator $path_locator) {
    $settings = $config_factory->get('package_manager.settings');
    $this->additionalTrustedComposerPlugins = array_map(
      [__CLASS__, 'normalizePackageName'],
      $settings->get('additional_trusted_composer_plugins')
    );
    $this->inspector = $inspector;
    $this->pathLocator = $path_locator;
  }

  /**
   * Normalizes a package name.
   *
   * @param string $package_name
   *   A package name.
   *
   * @return string
   *   The normalized package name.
   */
  private static function normalizePackageName(string $package_name): string {
    // Normalize the configured package names using Composer's own logic.
    return (new Package($package_name, 'irrelevant', 'irrelevant'))->getName();
  }

  /**
   * @return string[]
   */
  private function getSupportedPlugins(): array {
    return array_merge(
      array_keys(self::SUPPORTED_PLUGINS_THAT_DO_MODIFY),
      self::SUPPORTED_PLUGINS_THAT_DO_NOT_MODIFY,
      $this->additionalTrustedComposerPlugins,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    $stage = $event->getStage();

    // When about to copy the changes from the stage directory to the active
    // directory, use the stage directory's composer instead of the active.
    // Because composer plugins may be added or removed; the only thing that
    // matters is the set of composer plugins that *will* apply — if a composer
    // plugin is being removed, that's fine.
    $dir = $event instanceof PreApplyEvent
      ? $stage->getStageDirectory()
      : $this->pathLocator->getProjectRoot();
    try {
      // @see https://getcomposer.org/doc/06-config.md#allow-plugins
      $value = Json::decode($this->inspector->getConfig('allow-plugins', $dir));
    }
    catch (RuntimeException $exception) {
      $event->addErrorFromThrowable($exception, $this->t('Unable to determine Composer <code>allow-plugins</code> setting.'));
      return;
    }

    if ($value === 1) {
      $event->addError([$this->t('All composer plugins are allowed because <code>config.allow-plugins</code> is configured to <code>true</code>. This is an unacceptable security risk.')]);
      return;
    }

    // Only packages with `true` as a value are actually executed by composer.
    assert(is_array($value));
    $allowed_plugins = array_keys(array_filter($value));
    // Normalized allowed plugins: keys are normalized package names, values are
    // the original package names.
    $normalized_allowed_plugins = array_combine(
      array_map([__CLASS__, 'normalizePackageName'], $allowed_plugins),
      $allowed_plugins
    );
    $unsupported_plugins = array_diff_key($normalized_allowed_plugins, array_flip($this->getSupportedPlugins()));
    if ($unsupported_plugins) {
      $unsupported_plugins_messages = array_map(
        fn (string $raw_allowed_plugin_name) => new FormattableMarkup(
          "<code>@package_name</code>",
          [
            '@package_name' => $raw_allowed_plugin_name,
          ]
        ),
        $unsupported_plugins
      );
      $summary = $this->formatPlural(
        count($unsupported_plugins),
        'An unsupported Composer plugin was detected.',
        'Unsupported Composer plugins were detected.',
      );
      $event->addError($unsupported_plugins_messages, $summary);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'validateStagePreOperation',
      PreApplyEvent::class => 'validateStagePreOperation',
      StatusCheckEvent::class => 'validateStagePreOperation',
    ];
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Excludes site configuration files from stage directories.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class SiteConfigurationExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * The current site path, relative to the Drupal root.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * Constructs an ExcludedPathsSubscriber.
   *
   * @param string $site_path
   *   The current site path, relative to the Drupal root.
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(string $site_path, PathLocator $path_locator) {
    $this->sitePath = $site_path;
    $this->pathLocator = $path_locator;
  }

  /**
   * Excludes site configuration files from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeSiteConfiguration(CollectIgnoredPathsEvent $event): void {
    // Site configuration files are always excluded relative to the web root.
    $paths = [];

    // Ignore site-specific settings files, which are always in the web root.
    // By default, Drupal core will always try to write-protect these files.
    $settings_files = [
      'settings.php',
      'settings.local.php',
      'services.yml',
    ];
    foreach ($settings_files as $settings_file) {
      $paths[] = $this->sitePath . '/' . $settings_file;
      $paths[] = 'sites/default/' . $settings_file;
    }
    $this->excludeInWebRoot($event, $paths);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeSiteConfiguration',
    ];
  }

}

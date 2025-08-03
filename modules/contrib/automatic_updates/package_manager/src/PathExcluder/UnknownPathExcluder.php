<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Excludes unknown paths from stage operations.
 *
 * Any paths in the root directory of the project that are NOT one of the
 * following are considered unknown paths:
 * 1. The vendor directory
 * 2. The webroot
 * 3. composer.json
 * 4. composer.lock
 * 5. Scaffold files as determined by the drupal/core-composer-scaffold plugin
 * If web root and project root are same then nothing is excluded.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class UnknownPathExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * Constructs a UnknownPathExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeUnknownPaths',
    ];
  }

  /**
   * Excludes unknown paths from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeUnknownPaths(CollectIgnoredPathsEvent $event): void {
    $project_root = $this->pathLocator->getProjectRoot();
    $web_root = $project_root . DIRECTORY_SEPARATOR . $this->pathLocator->getWebRoot();
    if (realpath($web_root) === $project_root) {
      return;
    }
    $vendor_dir = $this->pathLocator->getVendorDirectory();
    // @todo Refactor in https://www.drupal.org/project/automatic_updates/issues/3334994.
    $core_packages = $event->getStage()->getActiveComposer()->getCorePackages();
    $scaffold_files_paths = $this->getScaffoldFiles($core_packages);
    $paths_in_project_root = glob("$project_root/*");
    $paths = [];
    $known_paths = array_merge([$vendor_dir, $web_root, "$project_root/composer.json", "$project_root/composer.lock"], $scaffold_files_paths);
    foreach ($paths_in_project_root as $path_in_project_root) {
      if (!in_array($path_in_project_root, $known_paths, TRUE)) {
        $paths[] = $path_in_project_root;
      }
    }
    $this->excludeInProjectRoot($event, $paths);
  }

  /**
   * Gets the path of scaffold files, for example 'index.php' and 'robots.txt'.
   *
   * @param string[] $core_packages
   *   The installed core packages.
   *
   * @return array
   *   The array of scaffold file paths.
   */
  private function getScaffoldFiles(array $core_packages): array {
    $scaffold_file_paths = [];
    /** @var \Composer\Package\PackageInterface $package */
    foreach ($core_packages as $package) {
      $core_composer_extra = $package->getExtra();
      if (array_key_exists('drupal-scaffold', $core_composer_extra)) {
        $scaffold_file_paths = array_merge($scaffold_file_paths, array_keys($core_composer_extra['drupal-scaffold']['file-mapping']));
      }
    }
    return $scaffold_file_paths;
  }

}

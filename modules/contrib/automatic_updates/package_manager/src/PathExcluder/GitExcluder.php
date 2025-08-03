<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\Core\File\FileSystemInterface;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

/**
 * Excludes .git directories from stage operations.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class GitExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a GitExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(PathLocator $path_locator, FileSystemInterface $file_system) {
    $this->pathLocator = $path_locator;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeGitDirectories',
    ];
  }

  /**
   * Excludes .git directories from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeGitDirectories(CollectIgnoredPathsEvent $event): void {
    // Find all .git directories in the project. We cannot do this with
    // FileSystemInterface::scanDirectory() because it unconditionally excludes
    // anything starting with a dot.
    $finder = Finder::create()
      ->in($this->pathLocator->getProjectRoot())
      ->directories()
      ->name('.git')
      ->ignoreVCS(FALSE)
      ->ignoreDotFiles(FALSE);

    $paths_to_exclude = [];

    $installed_paths = [];
    // Collect the paths of every installed package.
    $installed_packages = $event->getStage()->getActiveComposer()->getInstalledPackagesData();
    foreach ($installed_packages as $package_data) {
      if (array_key_exists('install_path', $package_data) && !empty($package_data['install_path'])) {
        $installed_paths[] = $this->fileSystem->realpath($package_data['install_path']);
      }
    }
    foreach ($finder as $git_directory) {
      // Don't exclude any `.git` directory that is directly under an installed
      // package's path, since it means Composer probably installed that package
      // from source and therefore needs the `.git` directory in order to update
      // the package.
      if (!in_array(dirname((string) $git_directory), $installed_paths, TRUE)) {
        $paths_to_exclude[] = $git_directory->getPathname();
      }
    }
    $this->excludeInProjectRoot($event, $paths_to_exclude);
  }

}

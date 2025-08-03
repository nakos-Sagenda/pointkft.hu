<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Excludes public and private files from stage operations.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class SiteFilesExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The Symfony file system service.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fileSystem;

  /**
   * Constructs a SiteFilesExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Symfony\Component\Filesystem\Filesystem $file_system
   *   The Symfony file system service.
   */
  public function __construct(PathLocator $path_locator, StreamWrapperManagerInterface $stream_wrapper_manager, Filesystem $file_system) {
    $this->pathLocator = $path_locator;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeSiteFiles',
    ];
  }

  /**
   * Excludes public and private files from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeSiteFiles(CollectIgnoredPathsEvent $event): void {
    // Ignore public and private files. These paths could be either absolute or
    // relative, depending on site settings. If they are absolute, treat them
    // as relative to the project root. Otherwise, treat them as relative to
    // the web root.
    foreach (['public', 'private'] as $scheme) {
      $wrapper = $this->streamWrapperManager->getViaScheme($scheme);
      if ($wrapper instanceof LocalStream) {
        $path = $wrapper->getDirectoryPath();

        if ($this->fileSystem->isAbsolutePath($path)) {
          $this->excludeInProjectRoot($event, [realpath($path)]);
        }
        else {
          $this->excludeInWebRoot($event, [$path]);
        }
      }
    }
  }

}

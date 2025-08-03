<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Computes file system paths that are needed to stage code changes.
 */
class PathLocator {

  /**
   * The absolute path of the running Drupal code base.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a PathLocator object.
   *
   * @param string $app_root
   *   The absolute path of the running Drupal code base.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(string $app_root, ConfigFactoryInterface $config_factory = NULL, FileSystemInterface $file_system = NULL) {
    $this->appRoot = $app_root;
    if (empty($config_factory)) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $config_factory argument is deprecated in automatic_updates:8.x-2.1 and will be required before automatic_updates:3.0.0. See https://www.drupal.org/node/3300008.', E_USER_DEPRECATED);
      $config_factory = \Drupal::configFactory();
    }
    $this->configFactory = $config_factory;
    if (empty($file_system)) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $file_system argument is deprecated in automatic_updates:8.x-2.1 and will be required before automatic_updates:3.0.0. See https://www.drupal.org/node/3300008.', E_USER_DEPRECATED);
      $file_system = \Drupal::service('file_system');
    }
    $this->fileSystem = $file_system;
  }

  /**
   * Returns the absolute path of the project root.
   *
   * This is where the project-level composer.json should normally be found, and
   * may or may not be the same path as the Drupal code base.
   *
   * @return string
   *   The absolute path of the project root.
   */
  public function getProjectRoot(): string {
    // Assume that the vendor directory is immediately below the project root.
    return realpath($this->getVendorDirectory() . DIRECTORY_SEPARATOR . '..');
  }

  /**
   * Returns the absolute path of the vendor directory.
   *
   * @return string
   *   The absolute path of the vendor directory.
   */
  public function getVendorDirectory(): string {
    // There may be multiple class loaders at work.
    // ClassLoader::getRegisteredLoaders() keeps track of them all, indexed by
    // the path of the vendor directory they load classes from.
    $loaders = ClassLoader::getRegisteredLoaders();

    // If there's only one class loader, we don't need to search for the right
    // one.
    if (count($loaders) === 1) {
      return key($loaders);
    }

    // To determine which class loader is the one for Drupal's vendor directory,
    // look for the loader whose vendor path starts the same way as the path to
    // this file.
    foreach (array_keys($loaders) as $path) {
      if (str_starts_with(__FILE__, dirname($path))) {
        return $path;
      }
    }
    // If we couldn't find a match, assume that the first registered class
    // loader is the one we want.
    return key($loaders);
  }

  /**
   * Returns the path of the Drupal installation, relative to the project root.
   *
   * @return string
   *   The path of the Drupal installation, relative to the project root and
   *   without leading or trailing slashes. Will return an empty string if the
   *   project root and Drupal root are the same.
   */
  public function getWebRoot(): string {
    $web_root = str_replace(trim($this->getProjectRoot(), DIRECTORY_SEPARATOR), '', trim($this->appRoot, DIRECTORY_SEPARATOR));
    return trim($web_root, DIRECTORY_SEPARATOR);
  }

  /**
   * Returns the directory where stage directories will be created.
   *
   * The stage root may be affected by site settings, so stages may wish to
   * cache the value returned by this method, to ensure that they use the same
   * stage root directory throughout their life cycle.
   *
   * @return string
   *   The absolute path of the directory where stage directories should be
   *   created.
   */
  public function getStagingRoot(): string {
    $site_id = $this->configFactory->get('system.site')->get('uuid');
    return $this->fileSystem->getTempDirectory() . DIRECTORY_SEPARATOR . '.package_manager' . $site_id;
  }

}

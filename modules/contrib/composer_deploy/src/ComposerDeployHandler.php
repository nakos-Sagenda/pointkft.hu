<?php

/**
 * @file
 * Contains \Drupal\composer_deploy\ComposerDeployHandler.
 */

namespace Drupal\composer_deploy;

use DrupalFinder\DrupalFinderComposerRuntime;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
class ComposerDeployHandler {

  protected $packages = [];

  /**
   * List of package prefixes.
   *
   * @var string[]
   */
  protected $prefixes = ['drupal'];

  /**
   * @var \DrupalFinder\DrupalFinder
   */
  protected $drupalFinder;

  public function __construct(DrupalFinderComposerRuntime $drupalFinder) {
    $this->drupalFinder = $drupalFinder;

    $packages = json_decode(file_get_contents($this->drupalFinder->getVendorDir() . '/composer/installed.json'), TRUE);
    // Composer 2.0 compatibility.
    // @see https://getcomposer.org/upgrade/UPGRADE-2.0.md
    $packages = $packages['packages'] ?? $packages;
    $this->packages = is_array($packages) ? $packages : [];
  }

  /**
   * @deprecated. Use \Drupal\composer_deploy\ComposerDeployHandler::getPackageByPath instead.
   */
  public function getPackage($projectName) {
    foreach ($this->packages as $package) {
      foreach ($this->prefixes as $prefix) {
        if ($package['name'] == $prefix . '/' . $projectName) {
          return $package;
        }
      }
    }
    return FALSE;
  }

  public function getPackageByPath($path) {
    foreach ($this->packages as $package) {
      if (isset($package['install-path'])) {
        $packagePath = $this->drupalFinder->getVendorDir() . '/composer/' . $package['install-path'];
        if (Path::isBasePath($packagePath, $path)) {
          return $package;
        }
      }
    }
    return FALSE;
  }

  /**
   * Set the package prefixes to check against.
   *
   * @param string[] $prefixes
   */
  public function setPrefixes(array $prefixes) {
    $this->prefixes = $prefixes;
  }

}

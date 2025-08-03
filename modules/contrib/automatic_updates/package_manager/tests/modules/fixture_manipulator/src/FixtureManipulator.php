<?php

namespace Drupal\fixture_manipulator;

use Composer\Semver\VersionParser;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Filesystem\Filesystem;

/**
 * It manipulates.
 */
class FixtureManipulator {

  /**
   * Whether changes are currently being committed.
   *
   * @var bool
   */
  private bool $committingChanges = FALSE;

  /**
   * Arguments to manipulator functions.
   *
   * @var array
   */
  private array $manipulatorArguments = [];

  /**
   * Whether changes have been committed.
   *
   * @var bool
   */
  protected bool $committed = FALSE;

  /**
   * The fixture directory.
   *
   * @var string
   */
  private string $dir;

  /**
   * Adds a package.
   *
   * If $package contains an `install_path` key, it should be relative to the
   * location of `installed.json` and `installed.php`, which are in
   * `vendor/composer`. For example, if the package would be installed at
   * `vendor/kirk/enterprise`, the install path should be `../kirk/enterprise`.
   * If the package would be installed outside of vendor (for example, a Drupal
   * module in the `modules` directory), it would be `../../modules/my_module`.
   *
   * @param array $package
   *   The package info that should be added to installed.json and
   *   installed.php. Must include the `name` and `type` keys.
   * @param bool $is_dev_requirement
   *   Whether or not the package is a development requirement.
   * @param bool $create_project
   *   Whether or not the project info.yml file should be created.
   */
  public function addPackage(array $package, bool $is_dev_requirement = FALSE, bool $create_project = TRUE): self {
    if (!$this->committingChanges) {
      $this->queueManipulation('addPackage', func_get_args());
      return $this;
    }
    foreach (['name', 'type'] as $required_key) {
      if (!isset($package[$required_key])) {
        throw new \UnexpectedValueException("The '$required_key' is required when calling ::addPackage().");
      }
    }
    if (!preg_match('/\w+\/\w+/', $package['name'])) {
      throw new \UnexpectedValueException(sprintf("'%s' is not a valid package name.", $package['name']));
    }
    $this->setPackage($package['name'], $package, FALSE, $is_dev_requirement);
    $drupal_project_types = [
      'drupal-module',
      'drupal-theme',
      'drupal-custom-module',
      'drupal-custom-theme',
    ];
    if (!$create_project || !in_array($package['type'], $drupal_project_types, TRUE)) {
      return $this;
    }
    if (empty($package['install_path'])) {
      throw new \LogicException("'install_path' is not set.");
    }
    $install_path = "vendor/composer/" . $package['install_path'];
    $this->addProjectAtPath($install_path);
    return $this;
  }

  /**
   * Modifies a package's installed info.
   *
   * See ::addPackage() for information on how the `install_path` key is
   * handled, if $package has it.
   *
   * @param string $name
   *   The name of the package to modify.
   * @param array $package
   *   The package info that should be updated in installed.json and
   *   installed.php.
   */
  public function modifyPackage(string $name, array $package): self {
    if (!$this->committingChanges) {
      $this->queueManipulation('modifyPackage', func_get_args());
      return $this;
    }
    $this->setPackage($name, $package, TRUE);
    return $this;
  }

  /**
   * Sets a package version.
   *
   * @param string $package_name
   *   The package name.
   * @param string $version
   *   The version.
   *
   * @return $this
   */
  public function setVersion(string $package_name, string $version): self {
    return $this->modifyPackage($package_name, ['version' => $version]);
  }

  /**
   * Removes a package.
   *
   * @param string $name
   *   The name of the package to remove.
   */
  public function removePackage(string $name): self {
    if (!$this->committingChanges) {
      $this->queueManipulation('removePackage', func_get_args());
      return $this;
    }
    $this->setPackage($name, NULL, TRUE);
    return $this;
  }

  /**
   * Changes a package's installation information in a particular directory.
   *
   * This function is internal and should not be called directly. Use
   * ::addPackage(), ::modifyPackage(), and ::removePackage() instead.
   *
   * @param string $pretty_name
   *   The name of the package to add, update, or remove.
   * @param array|null $package
   *   The package information to be set in installed.json and installed.php, or
   *   NULL to remove it. Will be merged into the existing information if the
   *   package is already installed.
   * @param bool $should_exist
   *   Whether or not the package is expected to already be installed.
   * @param bool|null $is_dev_requirement
   *   Whether or not the package is a developer requirement.
   */
  private function setPackage(string $pretty_name, ?array $package, bool $should_exist, ?bool $is_dev_requirement = NULL): void {
    // @see \Composer\Package\BasePackage::__construct()
    $name = strtolower($pretty_name);

    if ($should_exist && isset($is_dev_requirement)) {
      throw new \LogicException('Changing an existing project to a dev requirement is not supported');
    }
    $composer_folder = $this->dir . '/vendor/composer';

    $file = $composer_folder . '/installed.json';
    self::ensureFilePathIsWritable($file);

    $data = file_get_contents($file);
    $data = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);

    // If the package is already installed, find its numerical index.
    $position = NULL;
    for ($i = 0; $i < count($data['packages']); $i++) {
      if ($data['packages'][$i]['name'] === $name) {
        $position = $i;
        break;
      }
    }
    // Ensure that we actually expect to find the package already installed (or
    // not).
    $expected_package_message = $should_exist
      ? "Expected package '$pretty_name' to be installed, but it wasn't."
      : "Expected package '$pretty_name' to not be installed, but it was.";
    if ($should_exist !== isset($position)) {
      throw new \LogicException($expected_package_message);
    }

    if ($package) {
      $package = ['name' => $pretty_name] + $package;
      $install_json_package = array_diff_key($package, array_flip(['install_path']));
      // Composer will use 'version_normalized', if present, to determine the
      // version number.
      if (isset($install_json_package['version']) && !isset($install_json_package['version_normalized'])) {
        $parser = new VersionParser();
        $install_json_package['version_normalized'] = $parser->normalize($install_json_package['version']);
      }
    }

    if (isset($position)) {
      // If we're going to be updating the package data, merge the incoming data
      // into what we already have.
      if ($package) {
        $install_json_package = $install_json_package + $data['packages'][$position];
      }

      // Remove the existing package; the array will be re-keyed by
      // array_splice().
      array_splice($data['packages'], $position, 1);
      $is_existing_dev_package = in_array($name, $data['dev-package-names'], TRUE);
      $data['dev-package-names'] = array_diff($data['dev-package-names'], [$name]);
      $data['dev-package-names'] = array_values($data['dev-package-names']);
    }
    // Add the package back to the list, if we have data for it.
    if (isset($package)) {
      $data['packages'][] = $install_json_package;

      if ($is_dev_requirement || !empty($is_existing_dev_package)) {
        $data['dev-package-names'][] = $name;
      }
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    self::ensureFilePathIsWritable($file);

    $file = $composer_folder . '/installed.php';
    self::ensureFilePathIsWritable($file);

    $data = require $file;

    // Ensure that we actually expect to find the package already installed (or
    // not).
    if ($should_exist !== isset($data['versions'][$name])) {
      throw new \LogicException($expected_package_message);
    }
    if ($package) {
      // If an install path was provided, ensure it's relative.
      if (array_key_exists('install_path', $package)) {
        if (!str_starts_with($package['install_path'], '../')) {
          throw new \UnexpectedValueException("'install_path' must start with '../'.");
        }
      }
      $install_php_package = $should_exist ?
        NestedArray::mergeDeep($data['versions'][$name], $package) :
        $package;

      // The installation paths in $data will have been interpreted by the PHP
      // runtime, so make them all relative again by stripping $this->dir out.
      array_walk($data['versions'], function (array &$install_php_package) use ($composer_folder) : void {
        if (array_key_exists('install_path', $install_php_package)) {
          $install_php_package['install_path'] = str_replace("$composer_folder/", '', $install_php_package['install_path']);
        }
      });
      $data['versions'][$name] = $install_php_package;
    }
    else {
      unset($data['versions'][$name]);
    }

    $data = var_export($data, TRUE);
    $data = str_replace("'install_path' => '../", "'install_path' => __DIR__ . '/../", $data);
    file_put_contents($file, "<?php\nreturn $data;");
  }

  /**
   * Adds a project at a path.
   *
   * @param string $path
   *   The path.
   * @param string|null $project_name
   *   (optional) The project name. If none is specified the last part of the
   *   path will be used.
   * @param string|null $file_name
   *   (optional) The file name. If none is specified the project name will be
   *   used.
   */
  public function addProjectAtPath(string $path, ?string $project_name = NULL, ?string $file_name = NULL): self {
    if (!$this->committingChanges) {
      $this->queueManipulation('addProjectAtPath', func_get_args());
      return $this;
    }
    $path = $this->dir . "/$path";
    if (file_exists($path)) {
      throw new \LogicException("'$path' path already exists.");
    }
    $fs = new Filesystem();
    $fs->mkdir($path);
    if ($project_name === NULL) {
      $project_name = basename($path);
    }
    if ($file_name === NULL) {
      $file_name = "$project_name.info.yml";
    }
    file_put_contents("$path/$file_name", Yaml::encode(['project' => $project_name]));
    return $this;
  }

  /**
   * Modifies core packages.
   *
   * @param string $version
   *   Target version.
   */
  public function setCorePackageVersion(string $version): self {
    $this->setVersion('drupal/core', $version);
    $this->setVersion('drupal/core-recommended', $version);
    $this->setVersion('drupal/core-dev', $version);
    return $this;
  }

  /**
   * Modifies a package's installed info.
   *
   * See ::addPackage() for information on how the `install_path` key is
   * handled, if $package has it.
   *
   * @param array $additional_config
   *   The configuration to add.
   */
  public function addConfig(array $additional_config): self {
    if (empty($additional_config)) {
      throw new \InvalidArgumentException('No config to add.');
    }

    if (!$this->committingChanges) {
      $this->queueManipulation('addConfig', func_get_args());
      return $this;
    }

    $file = $this->dir . '/composer.json';
    self::ensureFilePathIsWritable($file);

    $data = file_get_contents($file);
    $data = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);

    $config = $data['config'] ?? [];
    $data['config'] = NestedArray::mergeDeep($config, $additional_config);

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    self::ensureFilePathIsWritable($file);

    return $this;
  }

  /**
   * Commits the changes to the directory.
   */
  public function commitChanges(string $dir): void {
    $this->doCommitChanges($dir);
    $this->committed = TRUE;
  }

  /**
   * Commits all the changes.
   *
   * @param string $dir
   *   The directory to commit the changes to.
   */
  protected function doCommitChanges(string $dir): void {
    if ($this->committed) {
      throw new \BadMethodCallException('Already committed.');
    }
    $this->dir = $dir;
    $this->committingChanges = TRUE;
    $manipulator_arguments = $this->getQueuedManipulationItems();
    $this->clearQueuedManipulationItems();
    foreach ($manipulator_arguments as $method => $argument_sets) {
      foreach ($argument_sets as $argument_set) {
        $this->{$method}(...$argument_set);
      }
    }
    $this->committed = TRUE;
    $this->committingChanges = FALSE;
  }

  /**
   * Ensure that changes were committed before object is destroyed.
   */
  public function __destruct() {
    if (!$this->committed && !empty($this->manipulatorArguments)) {
      throw new \LogicException('commitChanges() must be called.');
    }
  }

  /**
   * Ensures a path is writable.
   *
   * @param string $path
   *   The path.
   */
  private static function ensureFilePathIsWritable(string $path): void {
    if (!is_writable($path)) {
      throw new \LogicException("'$path' is not writable.");
    }
  }

  /**
   * Creates an empty .git folder after being provided a path.
   */
  public function addDotGitFolder(string $path): self {
    if (!$this->committingChanges) {
      $this->queueManipulation('addDotGitFolder', func_get_args());
      return $this;
    }
    $fs = new Filesystem();
    $git_directory_path = $path . "/.git";
    if (!is_dir($git_directory_path)) {
      $fs->mkdir($git_directory_path);
    }
    else {
      throw new \LogicException("A .git directory already exists at $path.");
    }
    return $this;
  }

  /**
   * Queues manipulation arguments to be called in ::doCommitChanges().
   *
   * @param string $method
   *   The method name.
   * @param array $arguments
   *   The arguments.
   */
  protected function queueManipulation(string $method, array $arguments): void {
    $this->manipulatorArguments[$method][] = $arguments;
  }

  /**
   * Clears all queued manipulation items.
   */
  protected function clearQueuedManipulationItems(): void {
    $this->manipulatorArguments = [];
  }

  /**
   * Gets all queued manipulation items.
   *
   * @return array
   *   The queued manipulation items as set by calls to ::queueManipulation().
   */
  protected function getQueuedManipulationItems(): array {
    return $this->manipulatorArguments;
  }

}

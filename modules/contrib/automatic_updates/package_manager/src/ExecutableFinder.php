<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use PhpTuf\ComposerStager\Infrastructure\Service\Finder\ExecutableFinder as StagerExecutableFinder;
use PhpTuf\ComposerStager\Infrastructure\Service\Finder\ExecutableFinderInterface;
use Symfony\Component\Process\ExecutableFinder as SymfonyExecutableFinder;

/**
 * An executable finder which looks for executable paths in configuration.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ExecutableFinder implements ExecutableFinderInterface {

  /**
   * The decorated executable finder.
   *
   * @var \PhpTuf\ComposerStager\Infrastructure\Service\Finder\ExecutableFinder
   */
  private $decorated;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs an ExecutableFinder object.
   *
   * @param \Symfony\Component\Process\ExecutableFinder $symfony_executable_finder
   *   The Symfony executable finder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(SymfonyExecutableFinder $symfony_executable_finder, ConfigFactoryInterface $config_factory) {
    $this->decorated = new StagerExecutableFinder($symfony_executable_finder);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function find(string $name): string {
    $executables = $this->configFactory->get('package_manager.settings')
      ->get('executables');

    return $executables[$name] ?? $this->decorated->find($name);
  }

}

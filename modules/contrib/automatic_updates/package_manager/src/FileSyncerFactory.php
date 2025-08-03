<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use PhpTuf\ComposerStager\Domain\Service\FileSyncer\FileSyncerInterface;
use PhpTuf\ComposerStager\Infrastructure\Factory\FileSyncer\FileSyncerFactory as StagerFileSyncerFactory;
use PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\PhpFileSyncer;
use PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\RsyncFileSyncer;
use Symfony\Component\Process\ExecutableFinder;

/**
 * A file syncer factory which creates a file syncer according to configuration.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class FileSyncerFactory {

  /**
   * The decorated file syncer factory.
   *
   * @var \PhpTuf\ComposerStager\Infrastructure\Factory\FileSyncer\FileSyncerFactory
   */
  protected $decorated;

  /**
   * The PHP file syncer service.
   *
   * @var \PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\PhpFileSyncer
   */
  protected $phpFileSyncer;

  /**
   * The rsync file syncer service.
   *
   * @var \PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\RsyncFileSyncer
   */
  protected $rsyncFileSyncer;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a FileCopierFactory object.
   *
   * @param \Symfony\Component\Process\ExecutableFinder $executable_finder
   *   The Symfony executable finder.
   * @param \PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\PhpFileSyncer $php_file_syncer
   *   The PHP file syncer service.
   * @param \PhpTuf\ComposerStager\Infrastructure\Service\FileSyncer\RsyncFileSyncer $rsync_file_syncer
   *   The rsync file syncer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ExecutableFinder $executable_finder, PhpFileSyncer $php_file_syncer, RsyncFileSyncer $rsync_file_syncer, ConfigFactoryInterface $config_factory) {
    $this->decorated = new StagerFileSyncerFactory($executable_finder, $php_file_syncer, $rsync_file_syncer);
    $this->phpFileSyncer = $php_file_syncer;
    $this->rsyncFileSyncer = $rsync_file_syncer;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function create(): FileSyncerInterface {
    $syncer = $this->configFactory->get('package_manager.settings')
      ->get('file_syncer');

    switch ($syncer) {
      case 'rsync':
        return $this->rsyncFileSyncer;

      case 'php':
        return $this->phpFileSyncer;

      default:
        return $this->decorated->create();
    }
  }

}

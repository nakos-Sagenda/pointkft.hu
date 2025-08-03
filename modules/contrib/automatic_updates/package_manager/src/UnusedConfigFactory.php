<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * Dummy Class.
 *
 * @internal
 *
 * @todo Remove this in https://www.drupal.org/i/3303167
 */
final class UnusedConfigFactory implements ConfigFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function getEditable($name) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $names) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function reset($name = NULL) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function rename($old_name, $new_name) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKeys() {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function clearStaticCache() {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function addOverride(ConfigFactoryOverrideInterface $config_factory_override) {
    throw new \LogicException();
  }

}

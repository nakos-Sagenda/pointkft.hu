<?php

declare(strict_types = 1);

namespace Drupal\fixture_manipulator;

/**
 * A fixture manipulator for the active directory.
 */
final class ActiveFixtureManipulator extends FixtureManipulator {

  /**
   * {@inheritdoc}
   */
  public function commitChanges(string $dir = NULL): void {
    if ($dir) {
      throw new \UnexpectedValueException("$dir cannot be specific for a ActiveFixtureManipulator instance");
    }
    $dir = \Drupal::service('package_manager.path_locator')->getProjectRoot();
    parent::doCommitChanges($dir);
  }

}

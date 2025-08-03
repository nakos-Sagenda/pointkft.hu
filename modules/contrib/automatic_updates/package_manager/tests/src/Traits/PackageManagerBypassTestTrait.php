<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Traits;

/**
 * Common functions for testing using the package_manager_bypass module.
 *
 * @internal
 */
trait PackageManagerBypassTestTrait {

  /**
   * Asserts the number of times an update was staged.
   *
   * @param int $attempted_times
   *   The expected number of times an update was staged.
   */
  protected function assertUpdateStagedTimes(int $attempted_times): void {
    /** @var \Drupal\package_manager_bypass\BypassedStagerServiceBase $beginner */
    $beginner = $this->container->get('package_manager.beginner');
    $this->assertCount($attempted_times, $beginner->getInvocationArguments());

    /** @var \Drupal\package_manager_bypass\BypassedStagerServiceBase $stager */
    $stager = $this->container->get('package_manager.stager');
    // If an update was attempted, then there will be at least two calls to the
    // stager: one to change the runtime constraints in composer.json, and
    // another to actually update the installed dependencies. If any dev
    // packages (like `drupal/core-dev`) are installed, there may also be an
    // additional call to change the dev constraints.
    $this->assertGreaterThanOrEqual($attempted_times * 2, count($stager->getInvocationArguments()));

    /** @var \Drupal\package_manager_bypass\BypassedStagerServiceBase $committer */
    $committer = $this->container->get('package_manager.committer');
    $this->assertEmpty($committer->getInvocationArguments());
  }

}

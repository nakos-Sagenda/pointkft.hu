<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates_extensions_test_api;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\package_manager_test_api\ApiController as PackageManagerApiController;

/**
 * Provides API endpoint to interact with stage directory in functional tests.
 */
class ApiController extends PackageManagerApiController {

  /**
   * {@inheritdoc}
   */
  protected $finishedRoute = 'automatic_updates_extensions_test_api.finish';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('automatic_updates_extensions.updater'),
      $container->get('package_manager.path_locator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function createAndApplyStage(Request $request): string {
    $id = $this->stage->begin($request->get('projects', []));
    $this->stage->stage();
    $this->stage->apply();
    return $id;
  }

}

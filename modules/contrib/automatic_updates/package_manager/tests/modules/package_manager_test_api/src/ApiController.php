<?php

declare(strict_types = 1);

namespace Drupal\package_manager_test_api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\Stage;
use Drupal\package_manager\UnusedConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides API endpoints to interact with a stage directory in functional test.
 */
class ApiController extends ControllerBase {

  /**
   * The route to redirect to after the stage has been applied.
   *
   * @var string
   */
  protected $finishedRoute = 'package_manager_test_api.finish';

  /**
   * The stage.
   *
   * @var \Drupal\package_manager\Stage
   */
  protected $stage;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  private $pathLocator;

  /**
   * Constructs an ApiController object.
   *
   * @param \Drupal\package_manager\Stage $stage
   *   The stage.
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(Stage $stage, PathLocator $path_locator) {
    $this->stage = $stage;
    $this->pathLocator = $path_locator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $stage = new Stage(
      // @todo Remove this in https://www.drupal.org/i/3303167
      new UnusedConfigFactory(),
      $container->get('package_manager.path_locator'),
      $container->get('package_manager.beginner'),
      $container->get('package_manager.stager'),
      $container->get('package_manager.committer'),
      $container->get('file_system'),
      $container->get('event_dispatcher'),
      $container->get('tempstore.shared'),
      $container->get('datetime.time')
    );
    return new static(
      $stage,
      $container->get('package_manager.path_locator')
    );
  }

  /**
   * Begins a stage life cycle.
   *
   * Creates a stage directory, requires packages into it, applies changes to
   * the active directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request. The runtime and dev dependencies are expected to be in
   *   either the query string or request body, under the 'runtime' and 'dev'
   *   keys, respectively. There may also be a 'files_to_return' key, which
   *   contains an array of file paths, relative to the project root, whose
   *   contents should be returned in the response.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A response that directs to the ::finish() method.
   *
   * @see ::finish()
   */
  public function run(Request $request): RedirectResponse {
    $id = $this->createAndApplyStage($request);
    $redirect_url = Url::fromRoute($this->finishedRoute)
      ->setRouteParameter('id', $id)
      ->setOption('query', [
        'files_to_return' => $request->get('files_to_return', []),
      ])
      ->setAbsolute()
      ->toString();

    return new RedirectResponse($redirect_url);
  }

  /**
   * Performs post-apply tasks and destroys the stage.
   *
   * @param string $id
   *   The stage ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request. There may be a 'files_to_return' key in either the query
   *   string or request body which contains an array of file paths, relative to
   *   the project root, whose contents should be returned in the response.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing an associative array of the contents of the
   *   files listed in the 'files_to_return' request key. The array will be
   *   keyed by path, relative to the project root.
   */
  public function finish(string $id, Request $request): JsonResponse {
    $this->stage->claim($id)->postApply();
    $this->stage->destroy();

    $dir = $this->pathLocator->getProjectRoot();
    $file_contents = [];
    foreach ($request->get('files_to_return', []) as $path) {
      $file_contents[$path] = file_get_contents($dir . '/' . $path);
    }
    return new JsonResponse($file_contents);
  }

  /**
   * Creates a stage, requires packages into it, and applies the changes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request. The runtime and dev dependencies are expected to be in
   *   either the query string or request body, under the 'runtime' and 'dev'
   *   keys, respectively. There may also be a 'files_to_return' key, which
   *   contains an array of file paths, relative to the project root, whose
   *   contents should be returned in the response.
   *
   * @return string
   *   Unique ID for the stage, which can be used to claim the stage before
   *   performing other operations on it. Calling code should store this ID for
   *   as long as the stage needs to exist.
   */
  protected function createAndApplyStage(Request $request) : string {
    $id = $this->stage->create();
    $this->stage->require(
      $request->get('runtime', []),
      $request->get('dev', [])
    );
    $this->stage->apply();
    return $id;
  }

}

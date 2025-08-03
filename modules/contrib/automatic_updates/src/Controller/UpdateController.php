<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Controller;

use Drupal\automatic_updates\BatchProcessor;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\package_manager\Validator\PendingUpdatesValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to handle various stages of an automatic update.
 *
 * @internal
 *   Controller classes are internal.
 */
final class UpdateController extends ControllerBase {

  /**
   * The pending updates validator.
   *
   * @var \Drupal\package_manager\Validator\PendingUpdatesValidator
   */
  protected $pendingUpdatesValidator;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The status checker.
   *
   * @var \Drupal\automatic_updates\Validation\StatusChecker
   */
  protected $statusChecker;

  /**
   * Constructs an UpdateController object.
   *
   * @param \Drupal\package_manager\Validator\PendingUpdatesValidator $pending_updates_validator
   *   The pending updates validator.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\automatic_updates\Validation\StatusChecker $status_checker
   *   The status checker service.
   */
  public function __construct(PendingUpdatesValidator $pending_updates_validator, StateInterface $state, RouteMatchInterface $route_match, StatusChecker $status_checker) {
    $this->pendingUpdatesValidator = $pending_updates_validator;
    $this->stateService = $state;
    $this->routeMatch = $route_match;
    $this->statusChecker = $status_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('package_manager.validator.pending_updates'),
      $container->get('state'),
      $container->get('current_route_match'),
      $container->get('automatic_updates.status_checker')
    );
  }

  /**
   * Redirects after staged changes are applied to the active directory.
   *
   * If there are any pending update hooks or post-updates, the user is sent to
   * update.php to run those. Otherwise, they are redirected to the status
   * report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the appropriate destination.
   */
  public function onFinish(Request $request): RedirectResponse {
    $this->statusChecker->run();
    if ($this->pendingUpdatesValidator->updatesExist()) {
      $message = $this->t('Please apply database updates to complete the update process.');
      $url = Url::fromRoute('system.db_update');
    }
    else {
      $message = $this->t('Update complete!');
      $url = Url::fromRoute('update.status');
      // Now that the update is done, we can put the site back online if it was
      // previously not in maintenance mode.
      if (!$request->getSession()->remove(BatchProcessor::MAINTENANCE_MODE_SESSION_KEY)) {
        $this->state()->set('system.maintenance_mode', FALSE);
        // @todo Remove once the core bug that shows the maintenance mode
        //   message after the site is out of maintenance mode is fixed in
        //   https://www.drupal.org/i/3279246.
        $status_messages = $this->messenger()->messagesByType(MessengerInterface::TYPE_STATUS);
        $status_messages = array_filter($status_messages, function (string $message) {
          return !str_starts_with($message, (string) $this->t('Operating in maintenance mode.'));
        });
        $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
        foreach ($status_messages as $status_message) {
          $this->messenger()->addStatus($status_message);
        }
      }
    }
    $this->messenger()->addStatus($message);
    return new RedirectResponse($url->setAbsolute()->toString());
  }

  /**
   * Redirects deprecated routes and sets an informative message.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function redirectDeprecatedRoute(Request $request): RedirectResponse {
    $route_name = $this->routeMatch->getRouteName();

    switch ($route_name) {
      case 'automatic_updates.module_update':
        $destination = 'update.module_update';
        break;

      case 'automatic_updates.theme_update':
        $destination = 'update.theme_update';
        break;

      case 'automatic_updates.report_update':
        $destination = 'update.report_update';
        break;

      default:
        throw new \InvalidArgumentException("Unknown route: '$route_name'");
    }
    $destination = Url::fromRoute($destination)
      ->setAbsolute()
      ->toString();

    $message = $this->t('This page was accessed from @deprecated_url, which is deprecated and will not work in the next major version of Automatic Updates. Please use <a href=":correct_url">@correct_url</a> instead.', [
      '@deprecated_url' => $request->getUri(),
      ':correct_url' => $destination,
      '@correct_url' => $destination,
    ]);
    $this->messenger()->addStatus($message);

    // 308 is a permanent redirect regardless of HTTP method.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Redirections
    return new RedirectResponse($destination, 308);
  }

}

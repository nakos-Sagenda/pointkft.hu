<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Controller;

use Drupal\automatic_updates\Validation\ValidationResultDisplayTrait;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * A controller for running status checks.
 *
 * @internal
 *   Controller classes are internal.
 */
final class StatusCheckController extends ControllerBase {

  use ValidationResultDisplayTrait;

  /**
   * The status checker service.
   *
   * @var \Drupal\automatic_updates\Validation\StatusChecker
   */
  protected $statusChecker;

  /**
   * Constructs a StatusCheckController object.
   *
   * @param \Drupal\automatic_updates\Validation\StatusChecker $status_checker
   *   The status checker service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(StatusChecker $status_checker, TranslationInterface $string_translation) {
    $this->statusChecker = $status_checker;
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('automatic_updates.status_checker'),
      $container->get('string_translation'),
    );
  }

  /**
   * Redirects deprecated readiness check route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function runReadiness(Request $request): RedirectResponse {
    $destination = Url::fromRoute('automatic_updates.status_check')
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

  /**
   * Run the status checks.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the status report page.
   */
  public function run(): RedirectResponse {
    $results = $this->statusChecker->run()->getResults();
    if (!$results) {
      // @todo Link "automatic updates" to documentation in
      //   https://www.drupal.org/node/3168405.
      // If there are no messages from the status checks, display a message that
      // the site is ready. If there are messages, the status report will
      // display them.
      $this->messenger()->addStatus($this->t('No issues found. Your site is ready for automatic updates'));
    }
    else {
      // Determine if any of the results are errors.
      $error_results = $this->statusChecker->getResults(SystemManager::REQUIREMENT_ERROR);
      // If there are any errors, display a failure message as an error.
      // Otherwise, display it as a warning.
      $severity = $error_results ? SystemManager::REQUIREMENT_ERROR : SystemManager::REQUIREMENT_WARNING;
      $failure_message = $this->getFailureMessageForSeverity($severity);
      if ($severity === SystemManager::REQUIREMENT_ERROR) {
        $this->messenger()->addError($failure_message);
      }
      else {
        $this->messenger()->addWarning($failure_message);
      }
    }
    // Set a redirect to the status report page. Any other page that provides a
    // link to this controller should include 'destination' in the query string
    // to ensure this redirect is overridden.
    // @see \Drupal\Core\EventSubscriber\RedirectResponseSubscriber::checkRedirectUrl()
    return $this->redirect('system.status');
  }

}

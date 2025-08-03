<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks that the environment has support for Package Manager.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class EnvironmentSupportValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The name of the environment variable to check.
   *
   * This environment variable, if defined, should be parseable by
   * \Drupal\Core\Url::fromUri() and link to an explanation of why Package
   * Manager is not supported in the current environment.
   *
   * @var string
   */
  public const VARIABLE_NAME = 'DRUPAL_PACKAGE_MANAGER_NOT_SUPPORTED_HELP_URL';

  /**
   * {@inheritdoc}
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    $message = $this->t('Package Manager is not supported by your environment.');

    $help_url = getenv(static::VARIABLE_NAME);
    if (empty($help_url)) {
      return;
    }
    // If the URL is not parseable, catch the exception that Url::fromUri()
    // would generate.
    try {
      $message = Link::fromTextAndUrl($message, Url::fromUri($help_url))
        ->toString();
    }
    catch (\InvalidArgumentException $e) {
      // No need to do anything here. The message just won't be a link.
    }
    $event->addError([$message]);
    // If Package Manager is unsupported, there's no point in doing any more
    // validation.
    $event->stopPropagation();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => ['validateStagePreOperation', 200],
      PreApplyEvent::class => ['validateStagePreOperation', 200],
      StatusCheckEvent::class => ['validateStagePreOperation', 200],
    ];
  }

}

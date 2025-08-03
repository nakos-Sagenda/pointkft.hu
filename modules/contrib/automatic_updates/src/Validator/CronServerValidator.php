<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\CronUpdater;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Validates that the current server configuration can run cron updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class CronServerValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The type of interface between the web server and the PHP runtime.
   *
   * @var string
   *
   * @see php_sapi_name()
   * @see https://www.php.net/manual/en/reserved.constants.php
   */
  protected static $serverApi = PHP_SAPI;

  /**
   * Constructs a CronServerValidator object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler) {
    $this->request = $request_stack->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Checks that the server is configured correctly to run cron updates.
   *
   * @param \Drupal\package_manager\Event\PreOperationStageEvent $event
   *   The event object.
   */
  public function checkServer(PreOperationStageEvent $event): void {
    if (!$event->getStage() instanceof CronUpdater) {
      return;
    }

    $current_port = (int) $this->request->getPort();

    $alternate_port = $this->configFactory->get('automatic_updates.settings')
      ->get('cron_port');
    // If no alternate port is configured, it's the same as the current port.
    $alternate_port = intval($alternate_port) ?: $current_port;

    if (static::$serverApi === 'cli-server' && $current_port === $alternate_port) {
      $message = $this->t('Your site appears to be running on the built-in PHP web server on port @port. Drupal cannot be automatically updated with this configuration unless the site can also be reached on an alternate port.', [
        '@port' => $current_port,
      ]);
      if ($this->moduleHandler->moduleExists('help')) {
        $url = Url::fromRoute('help.page')
          ->setRouteParameter('name', 'automatic_updates')
          ->setOption('fragment', 'cron-alternate-port')
          ->toString();

        $message = $this->t('@message See <a href=":url">the Automatic Updates help page</a> for more information on how to resolve this.', [
          '@message' => $message,
          ':url' => $url,
        ]);
      }

      $event->addError([$message]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'checkServer',
      PreApplyEvent::class => 'checkServer',
      StatusCheckEvent::class => 'checkServer',
    ];
  }

}

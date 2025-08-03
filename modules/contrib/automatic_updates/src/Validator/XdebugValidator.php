<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Drupal\package_manager\Event\PreApplyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\automatic_updates\CronUpdater;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\Validator\XdebugValidator as PackageManagerXdebugValidator;

/**
 * Performs validation if Xdebug is enabled.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class XdebugValidator extends PackageManagerXdebugValidator implements EventSubscriberInterface {

  /**
   * Performs validation if Xdebug is enabled.
   *
   * @param \Drupal\package_manager\Event\PreOperationStageEvent $event
   *   The event object.
   */
  public function validateXdebugOff(PreOperationStageEvent $event): void {
    $stage = $event->getStage();
    $warning = $this->checkForXdebug();

    if ($warning) {
      if ($stage instanceof CronUpdater) {
        // Cron updates are not allowed if Xdebug is enabled.
        $event->addError([$this->t("Xdebug is enabled, currently Cron Updates are not allowed while it is enabled. If Xdebug is not disabled you will not receive security and other updates during cron.")]);
      }
      elseif ($event instanceof StatusCheckEvent) {
        $event->addWarning($warning);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'validateXdebugOff',
      PreApplyEvent::class => 'validateXdebugOff',
      StatusCheckEvent::class => 'validateXdebugOff',
    ];
  }

}

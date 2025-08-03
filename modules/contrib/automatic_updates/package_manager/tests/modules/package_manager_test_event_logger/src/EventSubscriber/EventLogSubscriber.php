<?php

declare(strict_types = 1);

namespace Drupal\package_manager_test_event_logger\EventSubscriber;

use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostDestroyEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreDestroyEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\Event\StageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an event subscriber to test logging during events in Package Manager.
 */
final class EventLogSubscriber implements EventSubscriberInterface {

  /**
   * Logs all events in the stage life cycle.
   *
   * @param \Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   */
  public function logEventInfo(StageEvent $event): void {
    \Drupal::logger('package_manager_test_event_logger')->info('package_manager_test_event_logger-start: Event: ' . get_class($event) . ', Stage instance of: ' . get_class($event->getStage()) . ':package_manager_test_event_logger-end');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => ['logEventInfo'],
      PostCreateEvent::class => ['logEventInfo'],
      PreRequireEvent::class => ['logEventInfo'],
      PostRequireEvent::class => ['logEventInfo'],
      PreApplyEvent::class => ['logEventInfo'],
      PostApplyEvent::class => ['logEventInfo'],
      PreDestroyEvent::class => ['logEventInfo'],
      PostDestroyEvent::class => ['logEventInfo'],
    ];
  }

}

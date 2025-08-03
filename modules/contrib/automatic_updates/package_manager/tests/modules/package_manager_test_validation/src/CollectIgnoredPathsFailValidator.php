<?php

declare(strict_types = 1);

namespace Drupal\package_manager_test_validation;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Allows to test an excluder which fails on CollectIgnoredPathsEvent.
 */
class CollectIgnoredPathsFailValidator implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'callToComposer',
    ];
  }

  /**
   * Fails when composer.json is deleted to simulate failure on excluders.
   */
  public function callToComposer(CollectIgnoredPathsEvent $event) {
    $event->getStage()->getActiveComposer();
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\EventSubscriber;

use Drupal\automatic_updates\CronUpdater;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears stored validation results after certain config changes.
 *
 * @todo Move this functionality into StatusChecker when
 *   https://www.drupal.org/i/3275317#comment-14482995 is resolved.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The status checker service.
   *
   * @var \Drupal\automatic_updates\Validation\StatusChecker
   */
  protected $statusChecker;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\automatic_updates\Validation\StatusChecker $status_checker
   *   The status checker service.
   */
  public function __construct(StatusChecker $status_checker) {
    $this->statusChecker = $status_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Reacts when config is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event object.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();

    // If the path of the Composer executable has changed, the status check
    // results are likely to change as well.
    if ($config->getName() === 'package_manager.settings' && $event->isChanged('executables.composer')) {
      $this->statusChecker->clearStoredResults();
    }
    elseif ($config->getName() === 'automatic_updates.settings') {
      // We only send status check failure notifications if unattended updates
      // are enabled. If notifications were previously disabled but have been
      // re-enabled, or their sensitivity level has changed, clear the stored
      // results so that we'll send accurate notifications next time cron runs.
      if ($event->isChanged('cron') && $config->getOriginal('cron') === CronUpdater::DISABLED) {
        $this->statusChecker->clearStoredResults();
      }
      elseif ($event->isChanged('status_check_mail') && $config->get('status_check_mail') !== StatusCheckMailer::DISABLED) {
        $this->statusChecker->clearStoredResults();
      }
    }
  }

}

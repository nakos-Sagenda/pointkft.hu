<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\CronUpdater;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that cron runs frequently enough to perform automatic updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class CronFrequencyValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The error-level interval between cron runs, in seconds.
   *
   * If cron runs less frequently than this, an error will be raised during
   * validation. Defaults to 24 hours.
   *
   * @var int
   */
  protected const ERROR_INTERVAL = 86400;

  /**
   * The warning-level interval between cron runs, in seconds.
   *
   * If cron runs less frequently than this, a warning will be raised during
   * validation. Defaults to 3 hours.
   *
   * @var int
   */
  protected const WARNING_INTERVAL = 10800;

  /**
   * The cron frequency, in hours, to suggest in errors or warnings.
   *
   * @var int
   */
  protected const SUGGESTED_INTERVAL = self::WARNING_INTERVAL / 3600;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The cron updater service.
   *
   * @var \Drupal\automatic_updates\CronUpdater
   */
  protected $cronUpdater;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * CronFrequencyValidator constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\automatic_updates\CronUpdater $cron_updater
   *   The cron updater service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, StateInterface $state, TimeInterface $time, TranslationInterface $translation, CronUpdater $cron_updater, LockBackendInterface $lock) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->time = $time;
    $this->setStringTranslation($translation);
    $this->cronUpdater = $cron_updater;
    $this->lock = $lock;
  }

  /**
   * Validates that cron runs frequently enough to perform automatic updates.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  public function checkCronFrequency(StatusCheckEvent $event): void {
    // We only want to do this check if the stage belongs to Automatic Updates.
    if (!$event->getStage() instanceof CronUpdater) {
      return;
    }
    // If automatic updates are disabled during cron, there's nothing we need
    // to validate.
    if ($this->cronUpdater->getMode() === CronUpdater::DISABLED) {
      return;
    }
    elseif ($this->moduleHandler->moduleExists('automated_cron')) {
      $this->validateAutomatedCron($event);
    }
    else {
      $this->validateLastCronRun($event);
    }
  }

  /**
   * Validates the cron frequency according to Automated Cron settings.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  protected function validateAutomatedCron(StatusCheckEvent $event): void {
    $message = $this->t('Cron is not set to run frequently enough. <a href=":configure">Configure it</a> to run at least every @frequency hours or disable automated cron and run it via an external scheduling system.', [
      ':configure' => Url::fromRoute('system.cron_settings')->toString(),
      '@frequency' => static::SUGGESTED_INTERVAL,
    ]);

    $interval = $this->configFactory->get('automated_cron.settings')->get('interval');

    if ($interval > static::ERROR_INTERVAL) {
      $event->addError([$message]);
    }
    elseif ($interval > static::WARNING_INTERVAL) {
      $event->addWarning([$message]);
    }
  }

  /**
   * Validates the cron frequency according to the last cron run time.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  protected function validateLastCronRun(StatusCheckEvent $event): void {
    // If cron is running right now, cron is clearly being run recently enough!
    if (!$this->lock->lockMayBeAvailable('cron')) {
      return;
    }

    // Determine when cron last ran. If not known, use the time that Drupal was
    // installed, defaulting to the beginning of the Unix epoch.
    $cron_last = $this->state->get('system.cron_last', $this->state->get('install_time', 0));
    if ($this->time->getRequestTime() - $cron_last > static::WARNING_INTERVAL) {
      $event->addError([
        $this->t('Cron has not run recently. For more information, see the online handbook entry for <a href=":cron-handbook">configuring cron jobs</a> to run at least every @frequency hours.', [
          ':cron-handbook' => 'https://www.drupal.org/cron',
          '@frequency' => static::SUGGESTED_INTERVAL,
        ]),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'checkCronFrequency',
    ];
  }

}

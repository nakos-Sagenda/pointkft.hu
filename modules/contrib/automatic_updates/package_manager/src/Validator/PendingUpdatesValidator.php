<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that there are no pending database updates.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class PendingUpdatesValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Drupal root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The update registry service.
   *
   * @var \Drupal\Core\Update\UpdateRegistry
   */
  protected $updateRegistry;

  /**
   * Constructs an PendingUpdatesValidator object.
   *
   * @param string $app_root
   *   The Drupal root.
   * @param \Drupal\Core\Update\UpdateRegistry $update_registry
   *   The update registry service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   */
  public function __construct(string $app_root, UpdateRegistry $update_registry, TranslationInterface $translation) {
    $this->appRoot = $app_root;
    $this->updateRegistry = $update_registry;
    $this->setStringTranslation($translation);
  }

  /**
   * {@inheritdoc}
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    if ($this->updatesExist()) {
      $message = $this->t('Some modules have database schema updates to install. You should run the <a href=":update">database update script</a> immediately.', [
        ':update' => Url::fromRoute('system.db_update')->toString(),
      ]);
      $event->addError([$message]);
    }
  }

  /**
   * Checks if there are any pending update or post-update hooks.
   *
   * @return bool
   *   TRUE if there are any pending update or post-update hooks, FALSE
   *   otherwise.
   */
  public function updatesExist(): bool {
    require_once $this->appRoot . '/core/includes/install.inc';
    require_once $this->appRoot . '/core/includes/update.inc';

    drupal_load_updates();
    $hook_updates = update_get_update_list();
    $post_updates = $this->updateRegistry->getPendingUpdateFunctions();

    return $hook_updates || $post_updates;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'validateStagePreOperation',
      StatusCheckEvent::class => 'validateStagePreOperation',
      PreApplyEvent::class => 'validateStagePreOperation',
    ];
  }

}

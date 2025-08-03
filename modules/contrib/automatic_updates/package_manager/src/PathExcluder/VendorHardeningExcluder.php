<?php

declare(strict_types = 1);

namespace Drupal\package_manager\PathExcluder;

use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Excludes vendor hardening files from stage operations.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class VendorHardeningExcluder implements EventSubscriberInterface {

  use PathExclusionsTrait;

  /**
   * Constructs a VendorHardeningExcluder object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectIgnoredPathsEvent::class => 'excludeVendorHardeningFiles',
    ];
  }

  /**
   * Excludes vendor hardening files from stage operations.
   *
   * @param \Drupal\package_manager\Event\CollectIgnoredPathsEvent $event
   *   The event object.
   */
  public function excludeVendorHardeningFiles(CollectIgnoredPathsEvent $event): void {
    // If the core-vendor-hardening plugin (used in the legacy-project template)
    // is present, it may have written security hardening files in the vendor
    // directory. They should always be ignored.
    $vendor_dir = $this->pathLocator->getVendorDirectory();
    $this->excludeInProjectRoot($event, [
      $vendor_dir . '/web.config',
      $vendor_dir . '/.htaccess',
    ]);
  }

}

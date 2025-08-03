<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\Updater;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerUtility;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that scaffold files have appropriate permissions.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ScaffoldFilePermissionsValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a SiteDirectoryPermissionsValidator object.
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
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    // We only want to do this check if the stage belongs to Automatic Updates.
    if (!$event->getStage() instanceof Updater) {
      return;
    }
    $paths = [];

    // Figure out the absolute path of `sites/default`.
    $site_dir = $this->pathLocator->getProjectRoot();
    $web_root = $this->pathLocator->getWebRoot();
    if ($web_root) {
      $site_dir .= '/' . $web_root;
    }
    $site_dir .= '/sites/default';

    $stage = $event->getStage();
    $active_scaffold_files = $this->getDefaultSiteFilesFromScaffold($stage->getActiveComposer());

    // If the active directory and stage directory have different files
    // scaffolded into `sites/default` (i.e., files were added, renamed, or
    // deleted), the site directory itself must be writable for the changes to
    // be applied.
    if ($event instanceof PreApplyEvent) {
      $staged_scaffold_files = $this->getDefaultSiteFilesFromScaffold($stage->getStageComposer());

      if ($active_scaffold_files !== $staged_scaffold_files) {
        $paths[] = $site_dir;
      }
    }
    // The scaffolded files themselves must be writable, so that any changes to
    // them in the stage directory can be synced back to the active directory.
    foreach ($active_scaffold_files as $scaffold_file) {
      $paths[] = $site_dir . '/' . $scaffold_file;
    }

    // Flag messages about anything in $paths which exists, but isn't writable.
    $non_writable_files = array_filter($paths, function (string $path): bool {
      return file_exists($path) && !is_writable($path);
    });
    if ($non_writable_files) {
      // Re-key the messages in order to prevent false negative comparisons in
      // tests.
      $non_writable_files = array_values($non_writable_files);
      $event->addError($non_writable_files, $this->t('The following paths must be writable in order to update default site configuration files.'));
    }
  }

  /**
   * Returns the list of file names scaffolded into `sites/default`.
   *
   * @param \Drupal\package_manager\ComposerUtility $composer
   *   A Composer utility helper for a directory.
   *
   * @return string[]
   *   The names of files that are scaffolded into `sites/default`, stripped
   *   of the preceding path. For example,
   *   `[web-root]/sites/default/default.settings.php` will be
   *   `default.settings.php`. Will be sorted alphabetically. If the target
   *   directory doesn't have the `drupal/core` package installed, the returned
   *   array will be empty.
   */
  protected function getDefaultSiteFilesFromScaffold(ComposerUtility $composer): array {
    $installed = $composer->getInstalledPackages();

    if (array_key_exists('drupal/core', $installed)) {
      $extra = $installed['drupal/core']->getExtra();
      // We expect Drupal core to provide a list of scaffold files.
      $files = $extra['drupal-scaffold']['file-mapping'];
    }
    else {
      $files = [];
    }
    $files = array_keys($files);
    $files = preg_grep('/sites\/default\//', $files);
    $files = array_map('basename', $files);
    sort($files);

    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'validateStagePreOperation',
      PreApplyEvent::class => 'validateStagePreOperation',
      StatusCheckEvent::class => 'validateStagePreOperation',
    ];
  }

}

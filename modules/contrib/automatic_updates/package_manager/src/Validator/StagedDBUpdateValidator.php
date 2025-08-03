<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flags a warning if there are database updates in a staged update.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class StagedDBUpdateValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * The module list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The theme list service.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeList;

  /**
   * Constructs a StagedDBUpdateValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   The theme list service.
   */
  public function __construct(PathLocator $path_locator, ModuleExtensionList $module_list, ThemeExtensionList $theme_list) {
    $this->pathLocator = $path_locator;
    $this->moduleList = $module_list;
    $this->themeList = $theme_list;
  }

  /**
   * Checks that the staged update does not have changes to its install files.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  public function checkForStagedDatabaseUpdates(StatusCheckEvent $event): void {
    try {
      $stage_dir = $event->getStage()->getStageDirectory();
    }
    catch (\LogicException $e) {
      // Stage directory can't be determined, so there's nothing to validate.
      return;
    }

    $extensions_with_updates = $this->getExtensionsWithDatabaseUpdates($stage_dir);
    if ($extensions_with_updates) {
      $event->addWarning($extensions_with_updates, $this->t('Possible database updates have been detected in the following extensions.'));
    }
  }

  /**
   * Determines if a staged extension has changed update functions.
   *
   * @param string $stage_dir
   *   The path of the stage directory.
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to check.
   *
   * @return bool
   *   TRUE if the staged copy of the extension has changed update functions
   *   compared to the active copy, FALSE otherwise.
   *
   * @todo Use a more sophisticated method to detect changes in the staged
   *   extension. Right now, we just compare hashes of the .install and
   *   .post_update.php files in both copies of the given extension, but this
   *   will cause false positives for changes to comments, whitespace, or
   *   runtime code like requirements checks. It would be preferable to use a
   *   static analyzer to detect new or changed functions that are actually
   *   executed during an update. No matter what, this method must NEVER cause
   *   false negatives, since that could result in code which is incompatible
   *   with the current database schema being copied to the active directory.
   *
   * @see https://www.drupal.org/project/automatic_updates/issues/3253828
   */
  public function hasStagedUpdates(string $stage_dir, Extension $extension): bool {
    $active_dir = $this->pathLocator->getProjectRoot();

    $web_root = $this->pathLocator->getWebRoot();
    if ($web_root) {
      $active_dir .= DIRECTORY_SEPARATOR . $web_root;
      $stage_dir .= DIRECTORY_SEPARATOR . $web_root;
    }

    $active_hashes = $this->getHashes($active_dir, $extension);
    $staged_hashes = $this->getHashes($stage_dir, $extension);

    return $active_hashes !== $staged_hashes;
  }

  /**
   * Returns hashes of the .install and .post-update.php files for a module.
   *
   * @param string $root_dir
   *   The root directory of the Drupal code base.
   * @param \Drupal\Core\Extension\Extension $extension
   *   The module to check.
   *
   * @return string[]
   *   The hashes of the module's .install and .post_update.php files, in that
   *   order, if they exist. The array will be keyed by file extension.
   */
  protected function getHashes(string $root_dir, Extension $extension): array {
    $path = implode(DIRECTORY_SEPARATOR, [
      $root_dir,
      $extension->getPath(),
      $extension->getName(),
    ]);
    $hashes = [];

    foreach (['.install', '.post_update.php'] as $suffix) {
      $file = $path . $suffix;

      if (file_exists($file)) {
        $hashes[$suffix] = hash_file('sha256', $file);
      }
    }
    return $hashes;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'checkForStagedDatabaseUpdates',
    ];
  }

  /**
   * Gets extensions that have database updates in the stage directory.
   *
   * @param string $stage_dir
   *   The path of the stage directory.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The names of the extensions that have possible database updates.
   */
  public function getExtensionsWithDatabaseUpdates(string $stage_dir): array {
    $extensions_with_updates = [];
    // Check all installed extensions for database updates.
    $lists = [$this->moduleList, $this->themeList];
    foreach ($lists as $list) {
      foreach ($list->getAllInstalledInfo() as $name => $info) {
        if ($this->hasStagedUpdates($stage_dir, $list->get($name))) {
          $extensions_with_updates[] = $info['name'];
        }
      }
    }

    return $extensions_with_updates;
  }

}

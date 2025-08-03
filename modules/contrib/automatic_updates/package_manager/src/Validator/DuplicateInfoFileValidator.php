<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

/**
 * Validates the stage does not have duplicate info.yml not present in active.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class DuplicateInfoFileValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a DuplicateInfoFileValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   */
  public function __construct(PathLocator $path_locator) {
    $this->pathLocator = $path_locator;
  }

  /**
   * Validates the stage does not have duplicate info.yml not present in active.
   */
  public function validateDuplicateInfoFileInStage(PreApplyEvent $event): void {
    $active_dir = $this->pathLocator->getProjectRoot();
    $stage_dir = $event->getStage()->getStageDirectory();
    $active_info_files = $this->findInfoFiles($active_dir);
    $stage_info_files = $this->findInfoFiles($stage_dir);

    foreach ($stage_info_files as $stage_info_file => $stage_info_count) {
      if (isset($active_info_files[$stage_info_file])) {
        // Check if stage directory has more info.yml files matching
        // $stage_info_file than in the active directory.
        if ($stage_info_count > $active_info_files[$stage_info_file]) {
          $event->addError([
            $this->t('The stage directory has @stage_count instances of @stage_info_file as compared to @active_count in the active directory. This likely indicates that a duplicate extension was installed.', [
              '@stage_info_file' => $stage_info_file,
              '@stage_count' => $stage_info_count,
              '@active_count' => $active_info_files[$stage_info_file],
            ]),
          ]);
        }
      }
      // Check if stage directory has two or more info.yml files matching
      // $stage_info_file which are not in active directory.
      elseif ($stage_info_count > 1) {
        $event->addError([
          $this->t('The stage directory has @stage_count instances of @stage_info_file. This likely indicates that a duplicate extension was installed.', [
            '@stage_info_file' => $stage_info_file,
            '@stage_count' => $stage_info_count,
          ]),
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreApplyEvent::class => 'validateDuplicateInfoFileInStage',
    ];
  }

  /**
   * Recursively finds info.yml files in a directory.
   *
   * @param string $dir
   *   The path of the directory to check.
   *
   * @return int[]
   *   Array of count of info.yml files in the directory keyed by file name.
   */
  protected function findInfoFiles(string $dir): array {
    $info_files_finder = Finder::create()
      ->in($dir)
      ->ignoreUnreadableDirs()
      ->name('*.info.yml');
    $info_files = [];
    /** @var \Symfony\Component\Finder\SplFileInfo $info_file */
    foreach (iterator_to_array($info_files_finder) as $info_file) {
      if ($this->skipInfoFile($info_file->getPath())) {
        continue;
      }
      $file_name = $info_file->getFilename();
      $info_files[$file_name] = ($info_files[$file_name] ?? 0) + 1;
    }
    return $info_files;
  }

  /**
   * Determines if an info.yml file should be skipped.
   *
   * @param string $info_file_path
   *   The path of the info.yml file.
   *
   * @return bool
   *   TRUE if the info.yml file should be skipped, FALSE otherwise.
   */
  private function skipInfoFile(string $info_file_path): bool {
    $directories_to_skip = [
      DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures',
      DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'modules',
      DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'themes',
      DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'profiles',
    ];
    foreach ($directories_to_skip as $directory_to_skip) {
      // Skipping info.yml files in tests/fixtures, tests/modules, tests/themes,
      // tests/profiles because Drupal will not scan these directories when
      // doing extension discovery.
      if (str_contains($info_file_path, $directory_to_skip)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Exception\ApplyFailedException;

/**
 * Handles failure marker file operation.
 *
 * The failure marker is a file placed in the active directory while staged
 * code is copied back into it, and then removed afterwards. This allows us to
 * know if a commit operation failed midway through, which could leave the site
 * code base in an indeterminate state -- which, in the worst case scenario,
 * might render Drupal being unable to boot.
 */
final class FailureMarker {

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * Constructs a FailureMarker object.
   *
   * @param \Drupal\package_manager\PathLocator $pathLocator
   *   The path locator service.
   */
  public function __construct(PathLocator $pathLocator) {
    $this->pathLocator = $pathLocator;
  }

  /**
   * Gets the marker file path.
   *
   * @return string
   *   The absolute path of the marker file.
   */
  public function getPath(): string {
    return $this->pathLocator->getProjectRoot() . '/PACKAGE_MANAGER_FAILURE.json';
  }

  /**
   * Deletes the marker file.
   */
  public function clear(): void {
    unlink($this->getPath());
  }

  /**
   * Writes data to marker file.
   *
   * @param \Drupal\package_manager\Stage $stage
   *   The stage.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   Failure message to be added.
   */
  public function write(Stage $stage, TranslatableMarkup $message): void {
    $data = [
      'stage_class' => get_class($stage),
      'stage_file' => (new \ReflectionObject($stage))->getFileName(),
      'message' => $message,
    ];
    file_put_contents($this->getPath(), Json::encode($data));
  }

  /**
   * Asserts the failure file doesn't exist.
   *
   * @throws \Drupal\package_manager\Exception\ApplyFailedException
   *   Thrown if the marker file exists.
   */
  public function assertNotExists(): void {
    $path = $this->getPath();

    if (file_exists($path)) {
      $data = file_get_contents($path);
      try {
        $data = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $exception) {
        throw new ApplyFailedException('Failure marker file exists but cannot be decoded.', $exception->getCode(), $exception);
      }

      throw new ApplyFailedException($data['message']);
    }
  }

}

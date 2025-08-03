<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

use Drupal\package_manager\Stage;
use PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface;
use PhpTuf\ComposerStager\Infrastructure\Value\PathList\PathList;

/**
 * Defines an event that collects ignored paths.
 *
 * Ignored paths are completely ignored by Composer Stager. They are never
 * copied into the stage directory from the active directory, or vice-versa.
 */
class CollectIgnoredPathsEvent extends StageEvent implements PathListInterface {

  /**
   * The list of ignored paths.
   *
   * @var \PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface
   */
  protected PathListInterface $pathList;

  /**
   * {@inheritdoc}
   */
  public function __construct(Stage $stage) {
    parent::__construct($stage);
    $this->pathList = new PathList([]);
  }

  /**
   * {@inheritdoc}
   */
  public function add(array $paths): void {
    $this->pathList->add($paths);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll(): array {
    return $this->pathList->getAll();
  }

}

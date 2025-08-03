<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Prevents any module from being uninstalled if update is in process.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class PackageManagerUninstallValidator implements ModuleUninstallValidatorInterface, ContainerAwareInterface {

  use ContainerAwareTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $stage = new Stage(
      // @todo Remove this in https://www.drupal.org/i/3303167
      new UnusedConfigFactory(),
      $this->container->get('package_manager.path_locator'),
      $this->container->get('package_manager.beginner'),
      $this->container->get('package_manager.stager'),
      $this->container->get('package_manager.committer'),
      $this->container->get('file_system'),
      $this->container->get('event_dispatcher'),
      $this->container->get('tempstore.shared'),
      $this->container->get('datetime.time'),
      $this->container->get(PathFactoryInterface::class),
      $this->container->get('package_manager.failure_marker')
    );
    if ($stage->isAvailable() || !$stage->isApplying()) {
      return [];
    }
    if ($stage->isApplying()) {
      $reasons[] = $this->t('Modules cannot be uninstalled while Package Manager is applying staged changes to the active code base.');
    }
    return $reasons;
  }

}

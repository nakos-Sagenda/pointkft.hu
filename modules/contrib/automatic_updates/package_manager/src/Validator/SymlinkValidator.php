<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use PhpTuf\ComposerStager\Domain\Exception\PreconditionException;
use PhpTuf\ComposerStager\Domain\Service\Precondition\CodebaseContainsNoSymlinksInterface;
use PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface;
use PhpTuf\ComposerStager\Infrastructure\Value\PathList\PathList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flags errors if the project root or stage directory contain symbolic links.
 *
 * @todo Remove this when Composer Stager's PHP file copier handles symlinks
 *   without issues.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class SymlinkValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The path locator service.
   *
   * @var \Drupal\package_manager\PathLocator
   */
  protected $pathLocator;

  /**
   * The Composer Stager precondition that this validator wraps.
   *
   * @var \PhpTuf\ComposerStager\Domain\Service\Precondition\CodebaseContainsNoSymlinksInterface
   */
  protected $precondition;

  /**
   * The path factory service.
   *
   * @var \PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface
   */
  protected $pathFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a SymlinkValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $path_locator
   *   The path locator service.
   * @param \PhpTuf\ComposerStager\Domain\Service\Precondition\CodebaseContainsNoSymlinksInterface $precondition
   *   The Composer Stager precondition that this validator wraps.
   * @param \PhpTuf\ComposerStager\Infrastructure\Factory\Path\PathFactoryInterface $path_factory
   *   The path factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(PathLocator $path_locator, CodebaseContainsNoSymlinksInterface $precondition, PathFactoryInterface $path_factory, ModuleHandlerInterface $module_handler) {
    $this->pathLocator = $path_locator;
    $this->precondition = $precondition;
    $this->pathFactory = $path_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStagePreOperation(PreOperationStageEvent $event): void {
    $active_dir = $this->pathFactory->create($this->pathLocator->getProjectRoot());

    // The precondition requires us to pass both an active and stage directory,
    // so if the stage hasn't been created or claimed yet, use the directory
    // that contains this file, which contains only a few files and no symlinks,
    // as the stage directory. The precondition itself doesn't care if the
    // directory actually exists or not.
    try {
      $stage_dir = $event->getStage()->getStageDirectory();
    }
    catch (\LogicException $e) {
      $stage_dir = __DIR__;
    }
    $stage_dir = $this->pathFactory->create($stage_dir);

    try {
      $ignored_paths = $event->getExcludedPaths();
      $this->precondition->assertIsFulfilled($active_dir, $stage_dir, new PathList($ignored_paths));
    }
    catch (PreconditionException $e) {
      $message = $e->getMessage();

      // If the Help module is enabled, append a link to Package Manager's help
      // page.
      // @see package_manager_help()
      if ($this->moduleHandler->moduleExists('help')) {
        $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
          ->setOption('fragment', 'package-manager-faq-symlinks-found')
          ->toString();

        $message = $this->t('@message See <a href=":package-manager-help">the help page</a> for information on how to resolve the problem.', [
          '@message' => $message,
          ':package-manager-help' => $url,
        ]);
      }
      $event->addError([$message]);
    }
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

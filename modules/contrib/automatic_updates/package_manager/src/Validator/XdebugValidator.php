<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Validator;

use Drupal\Component\FileSystem\FileSystem as DrupalFilesystem;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Performs validation if Xdebug is enabled.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class XdebugValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Adds warning if Xdebug is enabled.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  public function validateXdebugOff(StatusCheckEvent $event): void {
    $warning = $this->checkForXdebug();
    if ($warning) {
      $event->addWarning($warning);
    }
  }

  /**
   * Checks if Xdebug is enabled and returns a warning if it is.
   *
   * @return array|null
   *   Returns an array of warnings or null if Xdebug isn't detected.
   */
  protected function checkForXdebug(): ?array {
    // Xdebug is allowed to be enabled while running tests, for debugging
    // purposes. It's just not allowed to be enabled while using Package Manager
    // in a real environment. Except when specifically testing this validator.
    // @see \Drupal\Tests\package_manager\Kernel\XdebugValidatorTest::testXdebugValidation()
    // @see \Drupal\Tests\automatic_updates\Kernel\StatusCheck\XdebugValidatorTest::simulateXdebugEnabled()
    if (self::insideTest() && !function_exists('xdebug_break_TESTED')) {
      return NULL;
    }

    if (function_exists('xdebug_break')) {
      return [
        $this->t('Xdebug is enabled, which may have a negative performance impact on Package Manager and any modules that use it.'),
      ];
    }
    return NULL;
  }

  /**
   * Whether this validator is running inside a test.
   *
   * @return bool
   */
  private static function insideTest(): bool {
    // @see \Drupal\Core\CoreServiceProvider::registerTest()
    $in_functional_test = drupal_valid_test_ua();
    // @see \Drupal\Core\DependencyInjection\DependencySerializationTrait::__wakeup()
    $in_kernel_test = isset($GLOBALS['__PHPUNIT_BOOTSTRAP']);
    // @see \Drupal\BuildTests\Framework\BuildTestBase::setUp()
    $in_build_test = str_contains(__FILE__, DrupalFilesystem::getOsTemporaryDirectory() . '/build_workspace_');
    return $in_functional_test || $in_kernel_test || $in_build_test;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'validateXdebugOff',
    ];
  }

}

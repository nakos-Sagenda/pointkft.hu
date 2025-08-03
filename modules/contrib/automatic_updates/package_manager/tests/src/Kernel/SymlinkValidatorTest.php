<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Url;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use PhpTuf\ComposerStager\Domain\Exception\PreconditionException;
use PhpTuf\ComposerStager\Domain\Service\Precondition\CodebaseContainsNoSymlinksInterface;
use PhpTuf\ComposerStager\Domain\Value\Path\PathInterface;
use PhpTuf\ComposerStager\Domain\Value\PathList\PathListInterface;
use PHPUnit\Framework\Assert;
use Prophecy\Argument;

/**
 * @covers \Drupal\package_manager\Validator\SymlinkValidator
 * @group package_manager
 * @internal
 */
class SymlinkValidatorTest extends PackageManagerKernelTestBase {

  /**
   * The mocked precondition that checks for symlinks.
   *
   * @var \PhpTuf\ComposerStager\Domain\Service\Precondition\CodebaseContainsNoSymlinksInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $precondition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->precondition = $this->prophesize(CodebaseContainsNoSymlinksInterface::class);
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->getDefinition('package_manager.validator.symlink')
      ->setArgument('$precondition', $this->precondition->reveal());
  }

  /**
   * Data provider for ::testSymlink().
   *
   * @return array[]
   *   The test cases.
   */
  public function providerSymlink(): array {
    $test_cases = [];
    foreach ([PreApplyEvent::class, PreCreateEvent::class, StatusCheckEvent::class] as $event) {
      $test_cases["$event event with no symlinks"] = [
        FALSE,
        [],
        $event,
      ];
      $test_cases["$event event with symlinks"] = [
        TRUE,
        [
          ValidationResult::createError([t('Symlinks were found.')]),
        ],
        $event,
      ];
    }
    return $test_cases;
  }

  /**
   * Tests that the validator invokes Composer Stager's symlink precondition.
   *
   * @param bool $symlinks_exist
   *   Whether or not the precondition will detect symlinks.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string $event
   *   The event to test.
   *
   * @dataProvider providerSymlink
   */
  public function testSymlink(bool $symlinks_exist, array $expected_results, string $event): void {
    $add_ignored_path = function (CollectIgnoredPathsEvent $event): void {
      $event->add(['ignore/me']);
    };
    $this->addEventTestListener($add_ignored_path, CollectIgnoredPathsEvent::class);

    // Expected argument types for active directory, stage directory and ignored
    // paths passed while checking if precondition is fulfilled.
    // @see \PhpTuf\ComposerStager\Domain\Service\Precondition\PreconditionInterface::assertIsFulfilled()
    $arguments = [
      Argument::type(PathInterface::class),
      Argument::type(PathInterface::class),
      Argument::type(PathListInterface::class),
    ];
    $listener = function () use ($arguments, $symlinks_exist): void {
      // Ensure that the Composer Stager's symlink precondition is invoked.
      $this->precondition->assertIsFulfilled(...$arguments)
        ->will(function (array $arguments) use ($symlinks_exist): void {
          // Ensure that 'ignore/me' is present in ignored paths.
          Assert::assertContains('ignore/me', $arguments[2]->getAll());

          // Whether to simulate or not that a symlink is found in the active
          // or staging directory (but outside the ignored paths).
          if ($symlinks_exist) {
            throw new PreconditionException($this->reveal(), 'Symlinks were found.');
          }
        })
        ->shouldBeCalled();
    };
    $this->addEventTestListener($listener, $event);
    if ($event === StatusCheckEvent::class) {
      $this->assertStatusCheckResults($expected_results);
    }
    else {
      $this->assertResults($expected_results, $event);
    }
  }

  /**
   * Tests the Composer Stager's symlink precondition with richer help.
   *
   * @param bool $symlinks_exist
   *   Whether or not the precondition will detect symlinks.
   * @param array $expected_results
   *   The expected validation results.
   * @param string $event
   *   The event to test.
   *
   * @dataProvider providerSymlink
   */
  public function testHelpLink(bool $symlinks_exist, array $expected_results, string $event): void {
    $this->enableModules(['help']);

    $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
      ->setOption('fragment', 'package-manager-faq-symlinks-found')
      ->toString();

    // Reformat the provided results so that they all have the link to the
    // online documentation appended to them.
    $map = function (string $message) use ($url): string {
      return $message . ' See <a href="' . $url . '">the help page</a> for information on how to resolve the problem.';
    };
    foreach ($expected_results as $index => $result) {
      $messages = array_map($map, $result->getMessages());
      $expected_results[$index] = ValidationResult::createError($messages);
    }
    $this->testSymlink($symlinks_exist, $expected_results, $event);
  }

}

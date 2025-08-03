<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\package_manager\Event\CollectIgnoredPathsEvent;
use Drupal\package_manager\Event\ExcludedPathsTrait;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostDestroyEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreDestroyEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\Event\StageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests that the stage fires events during its lifecycle.
 *
 * @covers \Drupal\package_manager\Event\StageEvent
 * @group package_manager
 * @internal
 */
class StageEventsTest extends PackageManagerKernelTestBase implements EventSubscriberInterface {

  /**
   * The events that were fired, in the order they were fired.
   *
   * @var string[]
   */
  private $events = [];

  /**
   * The stage under test.
   *
   * @var \Drupal\package_manager\Stage
   */
  private $stage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->stage = $this->createStage();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Since this test adds arbitrary event listeners that aren't services, we
    // need to ensure they will persist even if the container is rebuilt when
    // staged changes are applied.
    $container->getDefinition('event_dispatcher')->addTag('persist');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'handleEvent',
      PostCreateEvent::class => 'handleEvent',
      PreRequireEvent::class => 'handleEvent',
      PostRequireEvent::class => 'handleEvent',
      PreApplyEvent::class => 'handleEvent',
      PostApplyEvent::class => 'handleEvent',
      PreDestroyEvent::class => 'handleEvent',
      PostDestroyEvent::class => 'handleEvent',
    ];
  }

  /**
   * Handles a stage life cycle event.
   *
   * @param \Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   */
  public function handleEvent(StageEvent $event): void {
    array_push($this->events, get_class($event));

    // The event should have a reference to the stage which fired it.
    $this->assertSame($event->getStage(), $this->stage);
  }

  /**
   * Tests that the stage fires life cycle events in a specific order.
   */
  public function testEvents(): void {
    $this->container->get('event_dispatcher')->addSubscriber($this);

    $this->stage->create();
    $this->stage->require(['ext-json:*']);
    $this->stage->apply();
    $this->stage->postApply();
    $this->stage->destroy();

    $this->assertSame($this->events, [
      PreCreateEvent::class,
      PostCreateEvent::class,
      PreRequireEvent::class,
      PostRequireEvent::class,
      PreApplyEvent::class,
      PostApplyEvent::class,
      PreDestroyEvent::class,
      PostDestroyEvent::class,
    ]);
  }

  /**
   * Data provider for testValidationResults().
   *
   * @return string[][]
   *   The test cases.
   */
  public function providerValidationResults(): array {
    return [
      'PreCreateEvent' => [PreCreateEvent::class],
      'PreRequireEvent' => [PreRequireEvent::class],
      'PreApplyEvent' => [PreApplyEvent::class],
      'PreDestroyEvent' => [PreDestroyEvent::class],
    ];
  }

  /**
   * Tests that an exception is thrown if an event has validation results.
   *
   * @param string $event_class
   *   The event class to test.
   *
   * @dataProvider providerValidationResults
   */
  public function testValidationResults(string $event_class): void {
    $error_messages = [t('Burn, baby, burn')];
    // Set up an event listener which will only flag an error for the event
    // class under test.
    $handler = function (StageEvent $event) use ($event_class, $error_messages): void {
      if (get_class($event) === $event_class) {
        if ($event instanceof PreOperationStageEvent) {
          $event->addError($error_messages);
        }
      }
    };
    $this->addEventTestListener($handler, $event_class);

    $result = ValidationResult::createError($error_messages);
    $this->assertResults([$result], $event_class);
  }

  /**
   * Tests that pre- and post-require events have access to the package lists.
   */
  public function testPackageListsAvailableToRequireEvents(): void {
    $listener = function (object $event): void {
      $expected_runtime = ['drupal/core' => '9.8.2'];
      $expected_dev = ['drupal/core-dev' => '9.8.2'];

      /** @var \Drupal\package_manager\Event\PreRequireEvent|\Drupal\package_manager\Event\PostRequireEvent $event */
      $this->assertSame($expected_runtime, $event->getRuntimePackages());
      $this->assertSame($expected_dev, $event->getDevPackages());
    };
    $this->addEventTestListener($listener, PreRequireEvent::class);
    $this->addEventTestListener($listener, PostRequireEvent::class);

    $this->stage->create();
    $this->stage->require(['drupal/core:9.8.2'], ['drupal/core-dev:9.8.2']);
  }

  /**
   * @group legacy
   */
  public function testDeprecations(): void {
    $stage = $this->createStage();
    $this->expectDeprecation('Calling ' . StatusCheckEvent::class . '::__construct() without the $ignored_paths argument is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.');
    $this->expectDeprecation(ExcludedPathsTrait::class . '::excludePath() is deprecated in automatic_updates:8.x-2.5 and removed in automatic_updates:3.0.0. Use ' . CollectIgnoredPathsEvent::class . ' instead. See https://www.drupal.org/node/3317862.');
    (new StatusCheckEvent($stage))->excludePath('/junk/drawer');

    $this->expectDeprecation('Calling ' . PreCreateEvent::class . '::__construct() without the $ignored_paths argument is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.');
    $this->expectDeprecation(ExcludedPathsTrait::class . '::excludePath() is deprecated in automatic_updates:8.x-2.5 and removed in automatic_updates:3.0.0. Use ' . CollectIgnoredPathsEvent::class . ' instead. See https://www.drupal.org/node/3317862.');
    (new PreCreateEvent($stage))->excludePath('/junk/drawer');

    $this->expectDeprecation('Calling ' . PreApplyEvent::class . '::__construct() without the $ignored_paths argument is deprecated in automatic_updates:8.x-2.5 and will be removed in automatic_updates:3.0.0. See https://www.drupal.org/node/3317862.');
    $this->expectDeprecation(ExcludedPathsTrait::class . '::excludePath() is deprecated in automatic_updates:8.x-2.5 and removed in automatic_updates:3.0.0. Use ' . CollectIgnoredPathsEvent::class . ' instead. See https://www.drupal.org/node/3317862.');
    (new PreApplyEvent($stage))->excludePath('/junk/drawer');
  }

  /**
   * Tests exception is thrown if error is not added before stopPropagation().
   */
  public function testExceptionIfNoErrorBeforeStopPropagation(): void {
    $listener = function (PreCreateEvent $event): void {
      $event->stopPropagation();
    };
    $this->addEventTestListener($listener, PreCreateEvent::class);

    $this->expectException(TestStageValidationException::class);
    $this->expectExceptionMessage('Event propagation stopped without any errors added to the event. This bypasses the package_manager validation system.');
    $stage = $this->createStage();
    $stage->create();
  }

}

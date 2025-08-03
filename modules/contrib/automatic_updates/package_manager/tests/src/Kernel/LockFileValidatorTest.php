<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\Validator\LockFileValidator;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_bypass\NoOpStager;

/**
 * @coversDefaultClass \Drupal\package_manager\Validator\LockFileValidator
 * @group package_manager
 * @internal
 */
class LockFileValidatorTest extends PackageManagerKernelTestBase {

  /**
   * The path of the active directory in the test project.
   *
   * @var string
   */
  private $activeDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->activeDir = $this->container->get('package_manager.path_locator')
      ->getProjectRoot();
  }

  /**
   * Tests that if no active lock file exists, a stage cannot be created.
   *
   * @covers ::storeHash
   */
  public function testCreateWithNoLock(): void {
    unlink($this->activeDir . '/composer.lock');

    $no_lock = ValidationResult::createError([t('Could not hash the active lock file.')]);
    $stage = $this->assertResults([$no_lock], PreCreateEvent::class);
    // The stage was not created successfully, so the status check should be
    // clear.
    $this->assertStatusCheckResults([], $stage);
  }

  /**
   * Tests that if an active lock file exists, a stage can be created.
   *
   * @covers ::storeHash
   * @covers ::deleteHash
   */
  public function testCreateWithLock(): void {
    $this->assertResults([]);

    // Change the lock file to ensure the stored hash of the previous version
    // has been deleted.
    file_put_contents($this->activeDir . '/composer.lock', '{"changed": true}');
    $this->assertResults([]);
  }

  /**
   * Tests validation when the lock file has changed.
   *
   * @dataProvider providerValidateStageEvents
   */
  public function testLockFileChanged(string $event_class): void {
    // Add a listener with an extremely high priority to the same event that
    // should raise the validation error. Because the validator uses the default
    // priority of 0, this listener changes lock file before the validator
    // runs.
    $this->addEventTestListener(function () {
      $lock = json_decode(file_get_contents($this->activeDir . '/composer.lock'), TRUE);
      $lock['extra']['key'] = 'value';
      file_put_contents($this->activeDir . '/composer.lock', json_encode($lock, JSON_THROW_ON_ERROR));
    }, $event_class);
    $result = ValidationResult::createError([
      t('Unexpected changes were detected in composer.lock, which indicates that other Composer operations were performed since this Package Manager operation started. This can put the code base into an unreliable state and therefore is not allowed.'),
    ]);
    $stage = $this->assertResults([$result], $event_class);
    // A status check should agree that there is an error here.
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests validation when the lock file is deleted.
   *
   * @dataProvider providerValidateStageEvents
   */
  public function testLockFileDeleted(string $event_class): void {
    // Add a listener with an extremely high priority to the same event that
    // should raise the validation error. Because the validator uses the default
    // priority of 0, this listener deletes lock file before the validator
    // runs.
    $this->addEventTestListener(function () {
      unlink($this->activeDir . '/composer.lock');
    }, $event_class);
    $result = ValidationResult::createError([
      t('Could not hash the active lock file.'),
    ]);
    $stage = $this->assertResults([$result], $event_class);
    // A status check should agree that there is an error here.
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests validation when a stored hash of the active lock file is unavailable.
   *
   * @dataProvider providerValidateStageEvents
   */
  public function testNoStoredHash(string $event_class): void {
    $reflector = new \ReflectionClassConstant(LockFileValidator::class, 'STATE_KEY');
    $state_key = $reflector->getValue();

    // Add a listener with an extremely high priority to the same event that
    // should raise the validation error. Because the validator uses the default
    // priority of 0, this listener deletes stored hash before the validator
    // runs.
    $this->addEventTestListener(function () use ($state_key) {
      $this->container->get('state')->delete($state_key);
    }, $event_class);
    $result = ValidationResult::createError([
      t('Could not retrieve stored hash of the active lock file.'),
    ]);
    $stage = $this->assertResults([$result], $event_class);
    // A status check should agree that there is an error here.
    $this->assertStatusCheckResults([$result], $stage);
  }

  /**
   * Tests validation when the staged and active lock files are identical.
   */
  public function testApplyWithNoChange(): void {
    // Leave the staged lock file alone.
    NoOpStager::setLockFileShouldChange(FALSE);

    $result = ValidationResult::createError([
      t('There are no pending Composer operations.'),
    ]);
    $stage = $this->assertResults([$result], PreApplyEvent::class);
    // A status check shouldn't produce raise any errors, because it's only
    // during pre-apply that we care if there are any pending Composer
    // operations.
    $this->assertStatusCheckResults([], $stage);
  }

  /**
   * Tests StatusCheckEvent when the stage is available.
   */
  public function testStatusCheckAvailableStage():void {
    $this->assertStatusCheckResults([]);
  }

  /**
   * Data provider for test methods that validate the stage directory.
   *
   * @return string[][]
   *   The test cases.
   */
  public function providerValidateStageEvents(): array {
    return [
      'pre-require' => [
        PreRequireEvent::class,
      ],
      'pre-apply' => [
        PreApplyEvent::class,
      ],
    ];
  }

}

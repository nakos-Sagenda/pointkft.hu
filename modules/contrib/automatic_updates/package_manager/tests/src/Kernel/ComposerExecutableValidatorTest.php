<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Url;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Validator\ComposerExecutableValidator;
use Drupal\package_manager\ValidationResult;
use PhpTuf\ComposerStager\Domain\Exception\IOException;
use PhpTuf\ComposerStager\Domain\Exception\LogicException;
use PhpTuf\ComposerStager\Infrastructure\Service\Finder\ExecutableFinderInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @covers \Drupal\package_manager\Validator\ComposerExecutableValidator
 * @group package_manager
 * @internal
 */
class ComposerExecutableValidatorTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->getDefinition('package_manager.validator.composer_executable')
      ->setClass(TestComposerExecutableValidator::class);
    $container
      ->register('test.terrible_composer_finder', TestFailingComposerFinder::class);
  }

  /**
   * Tests that an error is raised if the Composer executable isn't found.
   */
  public function testErrorIfComposerNotFound(): void {
    $exception = new IOException("This is your regularly scheduled error.");
    TestComposerExecutableValidator::setCommandOutput($exception);

    // The validator should translate that exception into an error.
    $error = ValidationResult::createError([
      $exception->getMessage(),
    ]);
    $this->assertStatusCheckResults([$error]);
    $this->assertResults([$error], PreCreateEvent::class);

    $this->enableModules(['help']);
    $this->assertResultsWithHelp([$error], PreCreateEvent::class);
  }

  /**
   * Test RuntimeError is handled correctly.
   */
  public function testComposerNotFound(): void {
    // @see \PhpTuf\ComposerStager\Infrastructure\Service\Precondition\ComposerIsAvailable::getUnfulfilledStatusMessage()
    $exception = new \Exception('Composer cannot be found.');
    TestComposerExecutableValidator::setCommandOutput($exception);

    // Change ComposerRunnerInterface path to throw a LogicException.
    $definition = $this->container->getDefinition('PhpTuf\ComposerStager\Domain\Service\Precondition\ComposerIsAvailableInterface');
    $definition->setArgument(0, new Reference('test.terrible_composer_finder'));

    // The validator should translate that exception into an error.
    $error = ValidationResult::createError([
      $exception->getMessage(),
    ]);
    $this->assertStatusCheckResults([$error]);
    $this->assertResults([$error], PreCreateEvent::class);
  }

  /**
   * Tests error on pre-apply if the Composer executable isn't found.
   */
  public function testErrorIfComposerNotFoundDuringPreApply(): void {
    // Setting command output which doesn't raise error for pre-create event.
    TestComposerExecutableValidator::setCommandOutput("Composer version 2.2.12");
    $exception = new IOException("This is your regularly scheduled error.");

    $listener = function () use ($exception): void {
      TestComposerExecutableValidator::setCommandOutput($exception);
    };
    $this->addEventTestListener($listener);

    // The validator should translate that exception into an error.
    $error = ValidationResult::createError([
      $exception->getMessage(),
    ]);
    $stage = $this->assertResults([$error], PreApplyEvent::class);
    $stage->destroy(TRUE);

    // Setting command output which doesn't raise error for pre-create event.
    TestComposerExecutableValidator::setCommandOutput("Composer version 2.2.12");
    $this->enableModules(['help']);
    $this->addEventTestListener($listener);
    $this->assertResultsWithHelp([$error], PreApplyEvent::class, FALSE);
  }

  /**
   * Data provider for testComposerVersionValidation().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerComposerVersionValidation(): array {
    // Invalid or undetectable Composer versions will always produce the same
    // error.
    $invalid_version = ValidationResult::createError([t('The Composer version could not be detected.')]);

    // Unsupported Composer versions will report the detected version number
    // in the validation result, so we need a function to churn out those fake
    // results for the test method.
    $unsupported_version = function (string $version): ValidationResult {
      $minimum_version = ComposerExecutableValidator::MINIMUM_COMPOSER_VERSION_CONSTRAINT;

      return ValidationResult::createError([
        t('A Composer version which satisfies <code>@minimum_version</code> is required, but version @version was detected.', [
          '@minimum_version' => $minimum_version,
          '@version' => $version,
        ]),
      ]);
    };

    return [
      'Minimum version' => [
        '2.2.12',
        [],
      ],
      '2.2.13' => [
        '2.2.13',
        [],
      ],
      '2.3.6' => [
        '2.3.6',
        [],
      ],
      '2.4.1' => [
        '2.4.1',
        [],
      ],
      '2.2.11' => [
        '2.2.11',
        [$unsupported_version('2.2.11')],
      ],
      '2.3.4' => [
        '2.3.4',
        [$unsupported_version('2.3.4')],
      ],
      '2.1.6' => [
        '2.1.6',
        [$unsupported_version('2.1.6')],
      ],
      '1.10.22' => [
        '1.10.22',
        [$unsupported_version('1.10.22')],
      ],
      '1.7.3' => [
        '1.7.3',
        [$unsupported_version('1.7.3')],
      ],
      '2.0.0-alpha3' => [
        '2.0.0-alpha3',
        [$unsupported_version('2.0.0-alpha3')],
      ],
      '2.1.0-RC1' => [
        '2.1.0-RC1',
        [$unsupported_version('2.1.0-RC1')],
      ],
      '1.0.0-RC' => [
        '1.0.0-RC',
        [$unsupported_version('1.0.0-RC')],
      ],
      '1.0.0-beta1' => [
        '1.0.0-beta1',
        [$unsupported_version('1.0.0-beta1')],
      ],
      '1.9-dev' => [
        '1.9-dev',
        [$invalid_version],
      ],
      'Invalid version' => [
        '@package_version@',
        [$invalid_version],
      ],
    ];
  }

  /**
   * Tests validation of various Composer versions.
   *
   * @param string $reported_version
   *   The version of Composer that `composer --version` should report.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerComposerVersionValidation
   */
  public function testComposerVersionValidation(string $reported_version, array $expected_results): void {
    TestComposerExecutableValidator::setCommandOutput("Composer version $reported_version");

    // If the validator can't find a recognized, supported version of Composer,
    // it should produce errors.
    $this->assertStatusCheckResults($expected_results);
    $this->assertResults($expected_results, PreCreateEvent::class);

    $this->enableModules(['help']);
    $this->assertResultsWithHelp($expected_results, PreCreateEvent::class);
  }

  /**
   * Tests validation of various Composer versions on pre-apply.
   *
   * @param string $reported_version
   *   The version of Composer that `composer --version` should report.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerComposerVersionValidation
   */
  public function testComposerVersionValidationDuringPreApply(string $reported_version, array $expected_results): void {
    // Setting command output which doesn't raise error for pre-create event.
    TestComposerExecutableValidator::setCommandOutput("Composer version 2.2.12");
    $listener = function () use ($reported_version): void {
      TestComposerExecutableValidator::setCommandOutput("Composer version $reported_version");
    };
    $this->addEventTestListener($listener);

    // If the validator can't find a recognized, supported version of Composer,
    // it should produce errors.
    $stage = $this->assertResults($expected_results, PreApplyEvent::class);
    $stage->destroy(TRUE);

    // Setting command output which doesn't raise error for pre-create event.
    TestComposerExecutableValidator::setCommandOutput("Composer version 2.2.12");
    $this->enableModules(['help']);
    $this->addEventTestListener($listener);
    $this->assertResultsWithHelp($expected_results, PreApplyEvent::class, FALSE);
  }

  /**
   * Asserts that a set of validation results link to the Package Manager help.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string|null $event_class
   *   (optional) The class of the event which should return the results. Must
   *   be passed if $expected_results is not empty.
   * @param bool $assert_status_check
   *   (optional) Whether the status checks should be asserted. Defaults to
   *   TRUE.
   */
  private function assertResultsWithHelp(array $expected_results, string $event_class = NULL, bool $assert_status_check = TRUE): void {
    $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
      ->setOption('fragment', 'package-manager-faq-composer-not-found')
      ->toString();

    // Reformat the provided results so that they all have the link to the
    // online documentation appended to them.
    $map = function (string $message) use ($url): string {
      return $message . ' See <a href="' . $url . '">the help page</a> for information on how to configure the path to Composer.';
    };
    foreach ($expected_results as $index => $result) {
      $messages = array_map($map, $result->getMessages());
      $expected_results[$index] = ValidationResult::createError($messages);
    }
    if ($assert_status_check) {
      $this->assertStatusCheckResults($expected_results);
    }
    $this->assertResults($expected_results, $event_class);
  }

}

/**
 * A test-only version of ComposerExecutableValidator that returns set output.
 */
class TestComposerExecutableValidator extends ComposerExecutableValidator {

  /**
   * Sets the output of `composer --version`.
   *
   * @param string|\Throwable $output
   *   The output of the command, or an exception to throw.
   */
  public static function setCommandOutput($output): void {
    \Drupal::state()->set(static::class, $output);
  }

  /**
   * {@inheritdoc}
   */
  protected function runCommand(): string {
    $output = \Drupal::state()->get(static::class);
    Assert::assertNotNull($output, __CLASS__ . '::setCommandOutput() should have been called first 💩');
    if ($output instanceof \Throwable) {
      throw $output;
    }
    return $output;
  }

}

/**
 * A test-only version of ExecutableFinderInterface that throws LogicException.
 */
class TestFailingComposerFinder implements ExecutableFinderInterface {

  /**
   * {@inheritdoc}
   */
  public function find(string $name): string {
    throw new LogicException();
  }

}

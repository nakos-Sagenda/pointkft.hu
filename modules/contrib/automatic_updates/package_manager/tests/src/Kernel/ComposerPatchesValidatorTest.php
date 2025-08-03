<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Kernel;

use Drupal\Core\Url;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Symfony\Component\Process\Process;

/**
 * @covers \Drupal\package_manager\Validator\ComposerPatchesValidator
 * @group package_manager
 * @internal
 */
class ComposerPatchesValidatorTest extends PackageManagerKernelTestBase {

  /**
   * Data provider for testErrorDuringPreCreate().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerPatcherConfiguration(): array {
    return [
      'exit-on-patch-failure missing' => [
        FALSE,
        [
          ValidationResult::createError([
            t('The <code>composer-exit-on-patch-failure</code> key is not set to <code>true</code> in the <code>extra</code> section of <code>composer.json</code>.'),
          ], t('Problems detected related to the Composer plugin <code>cweagans/composer-patches</code>.')),
        ],
      ],
      'exit-on-patch-failure set' => [
        TRUE,
        [],
      ],
    ];
  }

  /**
   * Tests that the patcher configuration is validated during pre-create.
   *
   * @param bool $extra_key_set
   *   Whether to set key in extra part of root package.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   *  @dataProvider providerPatcherConfiguration()
   */
  public function testPatcherConfiguration(bool $extra_key_set, array $expected_results): void {
    $this->addPatcherToAllowedPlugins();
    $this->setRootRequires();
    if ($extra_key_set) {
      $this->setRootExtra();
    }
    $this->assertStatusCheckResults($expected_results);
    $this->assertResults($expected_results, PreCreateEvent::class);
  }

  /**
   * Data provider for testErrorDuringPreApply() and testHelpLink().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public function providerErrorDuringPreApply(): array {
    $summary = t('Problems detected related to the Composer plugin <code>cweagans/composer-patches</code>.');

    return [
      'composer-patches present in stage, but not present in active' => [
        FALSE,
        TRUE,
        [
          ValidationResult::createError([
            t('It cannot be installed by Package Manager.'),
            t('It must be a root dependency.'),
            t('The <code>composer-exit-on-patch-failure</code> key is not set to <code>true</code> in the <code>extra</code> section of <code>composer.json</code>.'),
          ], $summary),
        ],
        [
          'package-manager-faq-composer-patches-installed-or-removed',
          'package-manager-faq-composer-patches-not-a-root-dependency',
          NULL,
        ],
      ],
      'composer-patches removed in stage, but present in active' => [
        TRUE,
        FALSE,
        [
          ValidationResult::createError([
            t('It cannot be removed by Package Manager.'),
          ], $summary),
        ],
        [
          'package-manager-faq-composer-patches-installed-or-removed',
        ],
      ],
      'composer-patches present in stage and active' => [
        TRUE,
        TRUE,
        [],
        [],
      ],
      'composer-patches not present in stage and active' => [
        FALSE,
        FALSE,
        [],
        [],
      ],
    ];
  }

  /**
   * Tests the patcher's presence and configuration are validated on pre-apply.
   *
   * @param bool $in_active
   *   Whether patcher is installed in active.
   * @param bool $in_stage
   *   Whether patcher is installed in stage.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerErrorDuringPreApply
   */
  public function testErrorDuringPreApply(bool $in_active, bool $in_stage, array $expected_results): void {
    if ($in_active) {
      // Add patcher as a root dependency and set
      // `composer-exit-on-patch-failure` to true.
      $this->addPatcherToAllowedPlugins();
      $this->setRootRequires();
      $this->setRootExtra();
    }
    if ($in_stage && !$in_active) {
      // Simulate a stage directory where the patcher is installed.
      $this->getStageFixtureManipulator()
        ->addPackage([
          'name' => 'cweagans/composer-patches',
          'version' => '24.12.1999',
          'type' => 'composer-plugin',
        ]);
    }

    if (!$in_stage && $in_active) {
      $this->getStageFixtureManipulator()
        ->removePackage('cweagans/composer-patches');
    }

    $stage = $this->createStage();
    $stage->create();
    $stage_dir = $stage->getStageDirectory();
    $stage->require(['drupal/core:9.8.1']);
    $event = new StatusCheckEvent($stage, []);
    $this->container->get('event_dispatcher')->dispatch($event);
    $this->assertValidationResultsEqual($expected_results, $event->getResults(), NULL, $stage_dir);

    try {
      $stage->apply();
      // If we didn't get an exception, ensure we didn't expect any errors
      $this->assertSame([], $expected_results);
    }
    catch (TestStageValidationException $e) {
      $this->assertNotEmpty($expected_results);
      $this->assertValidationResultsEqual($expected_results, $e->getResults(), NULL, $stage_dir);
    }
  }

  /**
   * Tests that validation errors can carry links to help.
   *
   * @param bool $in_active
   *   Whether patcher is installed in active.
   * @param bool $in_stage
   *   Whether patcher is installed in stage.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string[] $help_page_sections
   *   An associative array of fragments (anchors) in the online help. The keys
   *   should be the numeric indices of the validation result messages which
   *   should link to those fragments.
   *
   * @dataProvider providerErrorDuringPreApply
   */
  public function testErrorDuringPreApplyWithHelp(bool $in_active, bool $in_stage, array $expected_results, array $help_page_sections): void {
    $this->enableModules(['help']);

    foreach ($expected_results as $result_index => $result) {
      $messages = $result->getMessages();

      foreach ($messages as $message_index => $message) {
        if ($help_page_sections[$message_index]) {
          // Get the link to the online documentation for the error message.
          $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
            ->setOption('fragment', $help_page_sections[$message_index])
            ->toString();
          // Reformat the provided results so that they all have the link to the
          // online documentation appended to them.
          $messages[$message_index] = $message . ' See <a href="' . $url . '">the help page</a> for information on how to resolve the problem.';
        }
      }
      $expected_results[$result_index] = ValidationResult::createError($messages, $result->getSummary());
    }
    $this->testErrorDuringPreApply($in_active, $in_stage, $expected_results);
  }

  /**
   * Add the installed patcher to allowed plugins.
   */
  private function addPatcherToAllowedPlugins(): void {
    (new ActiveFixtureManipulator())
      ->addConfig([
        'allow-plugins' => [
          'cweagans/composer-patches' => TRUE,
        ],
      ])
      ->commitChanges();
  }

  /**
   * Sets the cweagans/composer-patches as required package for root package.
   */
  private function setRootRequires(): void {
    $process = new Process(
      ['composer', 'require', "cweagans/composer-patches:@dev"],
      $this->container->get('package_manager.path_locator')->getProjectRoot()
    );
    $process->mustRun();
  }

  /**
   * Sets the composer-exit-on-patch-failure key in extra part of root package.
   */
  private function setRootExtra(): void {
    $process = new Process(
      ['composer', 'config', 'extra.composer-exit-on-patch-failure', 'true'],
      $this->container->get('package_manager.path_locator')->getProjectRoot()
    );
    $process->mustRun();
  }

}

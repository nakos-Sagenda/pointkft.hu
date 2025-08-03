<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use PhpTuf\ComposerStager\Domain\Exception\RuntimeException;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;
use PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ComposerRunnerInterface;

/**
 * Defines a class to get information from Composer.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ComposerInspector {

  /**
   * The Composer runner service from Composer Stager.
   *
   * @var \PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ComposerRunnerInterface
   */
  protected ComposerRunnerInterface $runner;

  /**
   * The JSON process output callback.
   *
   * @var \Drupal\package_manager\JsonProcessOutputCallback
   */
  private JsonProcessOutputCallback $jsonCallback;

  /**
   * Constructs a ComposerInspector object.
   *
   * @param \PhpTuf\ComposerStager\Domain\Service\ProcessRunner\ComposerRunnerInterface $runner
   *   The Composer runner service from Composer Stager.
   */
  public function __construct(ComposerRunnerInterface $runner) {
    $this->runner = $runner;
    $this->jsonCallback = new JsonProcessOutputCallback();
  }

  /**
   * Returns a config value from Composer.
   *
   * @param string $key
   *   The config key to get.
   * @param string $working_dir
   *   The working directory in which to run Composer.
   *
   * @return string|null
   *   The output data. Note that the caller must know the shape of the
   *   requested key's value: if it's a string, no further processing is needed,
   *   but if it is a boolean, an array or a map, JSON decoding should be
   *   applied.
   *
   * @see \Composer\Command\ConfigCommand::execute()
   */
  public function getConfig(string $key, string $working_dir) : ?string {
    // For whatever reason, PHPCS thinks that $output is not used, even though
    // it very clearly *is*. So, shut PHPCS up for the duration of this method.
    // phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $callback = new class () implements ProcessOutputCallbackInterface {

      /**
       * The command output.
       *
       * @var string
       */
      public string $output = '';

      /**
       * {@inheritdoc}
       */
      public function __invoke(string $type, string $buffer): void {
        if ($type === ProcessOutputCallbackInterface::OUT) {
          $this->output .= trim($buffer);
        }
      }

    };
    // phpcs:enable
    try {
      $this->runner->run(['config', $key, "--working-dir=$working_dir"], $callback);
    }
    catch (RuntimeException $e) {
      // Assume any error from `composer config` is about an undefined key-value
      // pair which may have a known default value.
      // @todo Remove this once https://github.com/composer/composer/issues/11302 lands and ships in a composer release.
      switch ($key) {
        // @see https://getcomposer.org/doc/04-schema.md#minimum-stability
        case 'minimum-stability':
          return 'stable';

        default:
          // Otherwise, re-throw the exception.
          throw $e;
      }
    }
    return $callback->output;
  }

  /**
   * Returns the current Composer version.
   *
   * @param string $working_dir
   *   The working directory in which to run Composer.
   *
   * @return string
   *   The Composer version.
   *
   * @throws \UnexpectedValueException
   *   Thrown if the expect data format is not found.
   */
  public function getVersion(string $working_dir): string {
    $this->runner->run(['--format=json', "--working-dir=$working_dir"], $this->jsonCallback);
    $data = $this->jsonCallback->getOutputData();
    if (isset($data['application']['name'])
      && isset($data['application']['version'])
      && $data['application']['name'] === 'Composer'
      && is_string($data['application']['version'])) {
      return $data['application']['version'];
    }
    throw new \UnexpectedValueException('Unable to determine Composer version');
  }

}

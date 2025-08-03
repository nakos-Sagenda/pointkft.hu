<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Component\Serialization\Json;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;

/**
 * A process callback for handling output in the JSON format.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class JsonProcessOutputCallback implements ProcessOutputCallbackInterface {

  /**
   * The output buffer.
   *
   * @var string
   */
  private string $outBuffer = '';

  /**
   * The error buffer.
   *
   * @var string
   */
  private string $errorBuffer = '';

  /**
   * {@inheritdoc}
   */
  public function __invoke(string $type, string $buffer): void {
    // @todo Support self::ERR output in
    //   https://www.drupal.org/project/automatic_updates/issues/3337504.
    if ($type === self::OUT) {
      $this->outBuffer .= $buffer;
      return;
    }
    elseif ($type === self::ERR) {
      $this->errorBuffer .= $buffer;
      return;
    }
    throw new \InvalidArgumentException("Unsupported output type: '$type'");
  }

  /**
   * Gets the output data.
   *
   * @return mixed|null
   *   The output data or NULL if there was an exception.
   *
   * @throws \Exception
   *   Thrown if error buffer was not empty.
   */
  public function getOutputData() {
    $error = $this->errorBuffer;
    $out = $this->outBuffer;
    $this->errorBuffer = '';
    $this->outBuffer = '';
    if ($error !== '') {
      // @todo Handle deprecations messages in the error output in
      //   https://drupal.org/i/3337667.
      throw new \Exception($error);
    }
    return Json::decode($out);
  }

}

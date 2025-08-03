<?php

declare(strict_types = 1);

namespace Drupal\Tests\package_manager\Unit;

use Drupal\package_manager\JsonProcessOutputCallback;
use Drupal\Tests\UnitTestCase;
use PhpTuf\ComposerStager\Domain\Service\ProcessOutputCallback\ProcessOutputCallbackInterface;

/**
 * @coversDefaultClass \Drupal\package_manager\JsonProcessOutputCallback
 *
 * @group package_manager
 */
class JsonProcessOutputCallbackTest extends UnitTestCase {

  /**
   * @covers ::__invoke
   * @covers ::getOutputData
   */
  public function testGetOutputData(): void {
    // Create a data array that has 1 '*' character to allow easily splitting
    // up the JSON encoded string over multiple lines.
    $data = [
      'value' => 'I have value!*',
      'another value' => 'I have another value!',
      'one' => 1,
    ];
    $json_string = json_encode($data, JSON_THROW_ON_ERROR);
    $lines = explode('*', $json_string);
    $lines[0] .= '*';
    // Confirm that 2 string concatenated together will recreate the original
    // data.
    $this->assertSame($data, json_decode($lines[0] . $lines[1], TRUE));
    $callback = new JsonProcessOutputCallback();
    $callback(ProcessOutputCallbackInterface::OUT, $lines[0]);
    $callback(ProcessOutputCallbackInterface::OUT, $lines[1]);
    $this->assertSame($data, $callback->getOutputData());

    $callback = new JsonProcessOutputCallback();
    $callback(ProcessOutputCallbackInterface::OUT, '1');
    $this->assertSame(1, $callback->getOutputData());
  }

  /**
   * @covers ::getOutputData
   */
  public function testNoInvokeCall(): void {
    $callback = new JsonProcessOutputCallback();
    $this->assertSame(NULL, $callback->getOutputData());
  }

  /**
   * @covers ::getOutputData
   */
  public function testError(): void {
    $callback = new JsonProcessOutputCallback();
    $callback(ProcessOutputCallbackInterface::OUT, '1');
    $callback(ProcessOutputCallbackInterface::ERR, 'Oh no, what happened!!!!!');
    $callback(ProcessOutputCallbackInterface::OUT, '2');
    $callback(ProcessOutputCallbackInterface::ERR, 'Really what happened????');
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Oh no, what happened!!!!!Really what happened????');
    $callback->getOutputData();
  }

}

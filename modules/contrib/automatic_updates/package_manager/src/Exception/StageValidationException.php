<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Exception;

/**
 * Exception thrown if a stage has validation errors.
 *
 * Should not be thrown by external code.
 */
class StageValidationException extends StageException {

  /**
   * Any relevant validation results.
   *
   * @var \Drupal\package_manager\ValidationResult[]
   */
  protected $results = [];

  /**
   * Constructs a StageException object.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   Any relevant validation results.
   * @param string $message
   *   (optional) The exception message. Defaults to a plain text representation
   *   of the validation results.
   * @param mixed ...$arguments
   *   Additional arguments to pass to the parent constructor.
   */
  public function __construct(array $results = [], string $message = '', ...$arguments) {
    $this->results = $results;
    parent::__construct($message ?: $this->getResultsAsText(), ...$arguments);
  }

  /**
   * Gets the validation results.
   *
   * @return \Drupal\package_manager\ValidationResult[]
   *   The validation results.
   */
  public function getResults(): array {
    return $this->results;
  }

  /**
   * Formats the validation results as plain text.
   *
   * @return string
   *   The results, formatted as plain text.
   */
  protected function getResultsAsText(): string {
    $text = '';

    foreach ($this->getResults() as $result) {
      $messages = $result->getMessages();
      $summary = $result->getSummary();
      if ($summary) {
        array_unshift($messages, $summary);
      }
      $text .= implode("\n", $messages) . "\n";
    }
    return $text;
  }

}

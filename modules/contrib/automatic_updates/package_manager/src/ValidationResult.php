<?php

declare(strict_types = 1);

namespace Drupal\package_manager;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\SystemManager;

/**
 * A value object to contain the results of a validation.
 */
final class ValidationResult {

  /**
   * A succinct summary of the results.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $summary;

  /**
   * The error messages.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[]
   */
  protected $messages;

  /**
   * The severity of the result.
   *
   * @var int
   */
  protected $severity;

  /**
   * Creates a ValidationResult object.
   *
   * @param int $severity
   *   The severity of the result. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[] $messages
   *   The error messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   */
  private function __construct(int $severity, array $messages, ?TranslatableMarkup $summary = NULL) {
    if (empty($messages)) {
      throw new \InvalidArgumentException('At least one message is required.');
    }
    if (count($messages) > 1 && !$summary) {
      throw new \InvalidArgumentException('If more than one message is provided, a summary is required.');
    }
    $this->summary = $summary;
    $this->messages = $messages;
    $this->severity = $severity;
  }

  /**
   * Creates an error ValidationResult object from a throwable.
   *
   * @param \Throwable $throwable
   *   The throwable.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createErrorFromThrowable(\Throwable $throwable, ?TranslatableMarkup $summary = NULL): self {
    return new static(SystemManager::REQUIREMENT_ERROR, [$throwable->getMessage()], $summary);
  }

  /**
   * Creates an error ValidationResult object.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   The error messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createError(array $messages, ?TranslatableMarkup $summary = NULL): self {
    return new static(SystemManager::REQUIREMENT_ERROR, $messages, $summary);
  }

  /**
   * Creates a warning ValidationResult object.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   The error messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createWarning(array $messages, ?TranslatableMarkup $summary = NULL): self {
    return new static(SystemManager::REQUIREMENT_WARNING, $messages, $summary);
  }

  /**
   * Gets the summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The summary.
   */
  public function getSummary(): ?TranslatableMarkup {
    return $this->summary;
  }

  /**
   * Gets the messages.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[]
   *   The error or warning messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * The severity of the result.
   *
   * @return int
   *   Either SystemManager::REQUIREMENT_ERROR or
   *   SystemManager::REQUIREMENT_WARNING.
   */
  public function getSeverity(): int {
    return $this->severity;
  }

  /**
   * Returns the overall severity for a set of validation results.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   *
   * @return int
   *   The overall severity of the results. Will be be one of the
   *   SystemManager::REQUIREMENT_* constants.
   */
  public static function getOverallSeverity(array $results): int {
    foreach ($results as $result) {
      if ($result->getSeverity() === SystemManager::REQUIREMENT_ERROR) {
        return SystemManager::REQUIREMENT_ERROR;
      }
    }
    // If there were no errors, then any remaining results must be warnings.
    return $results ? SystemManager::REQUIREMENT_WARNING : SystemManager::REQUIREMENT_OK;
  }

  /**
   * Determines if two validation results are equivalent.
   *
   * @param self $a
   *   A validation result.
   * @param self $b
   *   Another validation result.
   *
   * @return bool
   *   TRUE if the given validation results have the same severity, summary,
   *   and messages (in the same order); otherwise FALSE.
   */
  public static function isEqual(self $a, self $b): bool {
    return (
      $a->getSeverity() === $b->getSeverity() &&
      strval($a->getSummary()) === strval($b->getSummary()) &&
      array_map('strval', $a->getMessages()) === array_map('strval', $b->getMessages())
    );
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;

/**
 * Base class for update forms provided by Automatic Updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not extend this class.
 */
abstract class UpdateFormBase extends FormBase {

  use StatusCheckTrait;

  /**
   * Gets a message, based on severity, when status checks fail.
   *
   * @param int $severity
   *   The severity. Should be one of the SystemManager::REQUIREMENT_*
   *   constants.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   *
   * @see \Drupal\system\SystemManager::REQUIREMENT_ERROR
   * @see \Drupal\system\SystemManager::REQUIREMENT_WARNING
   */
  protected function getFailureMessageForSeverity(int $severity): TranslatableMarkup {
    return $severity === SystemManager::REQUIREMENT_WARNING ?
      // @todo Link "automatic updates" to documentation in
      //   https://www.drupal.org/node/3168405.
      $this->t('Your site does not pass some readiness checks for automatic updates. Depending on the nature of the failures, it might affect the eligibility for automatic updates.') :
      $this->t('Your site does not pass some readiness checks for automatic updates. It cannot be automatically updated until further action is performed.');
  }

  /**
   * Adds a set of validation results to the messages.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  protected function displayResults(array $results, RendererInterface $renderer): void {
    $severity = ValidationResult::getOverallSeverity($results);

    if ($severity === SystemManager::REQUIREMENT_OK) {
      return;
    }

    // Format the results as a single item list prefixed by a preamble message.
    $build = [
      '#theme' => 'item_list__automatic_updates_validation_results',
      '#prefix' => $this->getFailureMessageForSeverity($severity),
    ];
    foreach ($results as $result) {
      $messages = $result->getMessages();

      // If there's a summary, there's guaranteed to be at least one message,
      // so render the result as a nested list.
      $summary = $result->getSummary();
      if ($summary) {
        $build['#items'][] = [
          '#theme' => $build['#theme'],
          '#prefix' => $summary,
          '#items' => $messages,
        ];
      }
      else {
        $build['#items'][] = reset($messages);
      }
    }
    $message = $renderer->renderRoot($build);

    if ($severity === SystemManager::REQUIREMENT_ERROR) {
      $this->messenger()->addError($message);
    }
    else {
      $this->messenger()->addWarning($message);
    }
  }

}

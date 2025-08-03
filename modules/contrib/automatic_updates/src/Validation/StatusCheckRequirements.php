<?php

declare(strict_types = 1);

namespace Drupal\automatic_updates\Validation;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for generating the status checkers' output for hook_requirements().
 *
 * @see automatic_updates_requirements()
 *
 * @internal
 *   This class implements logic to output the messages from status checkers
 *   on the status report page. It should not be called directly.
 */
final class StatusCheckRequirements implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use ValidationResultDisplayTrait;

  /**
   * The status checker service.
   *
   * @var \Drupal\automatic_updates\Validation\StatusChecker
   */
  protected $statusChecker;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a StatusCheckRequirements object.
   *
   * @param \Drupal\automatic_updates\Validation\StatusChecker $status_checker
   *   The status checker service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(StatusChecker $status_checker, TranslationInterface $translation, DateFormatterInterface $date_formatter) {
    $this->statusChecker = $status_checker;
    $this->setStringTranslation($translation);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('automatic_updates.status_checker'),
      $container->get('string_translation'),
      $container->get('date.formatter')
    );
  }

  /**
   * Gets requirements arrays as specified in hook_requirements().
   *
   * @return mixed[]
   *   Requirements arrays as specified by hook_requirements().
   */
  public function getRequirements(): array {
    $results = $this->statusChecker->run()->getResults();
    $requirements = [];
    if (empty($results)) {
      $requirements['automatic_updates_status_check'] = [
        'title' => $this->t('Update readiness checks'),
        'severity' => SystemManager::REQUIREMENT_OK,
        // @todo Link "automatic updates" to documentation in
        //   https://www.drupal.org/node/3168405.
        'value' => $this->t('Your site is ready for automatic updates.'),
      ];
      $run_link = $this->createRunLink();
      if ($run_link) {
        $requirements['automatic_updates_status_check']['description'] = $run_link;
      }
    }
    else {
      foreach ([SystemManager::REQUIREMENT_WARNING, SystemManager::REQUIREMENT_ERROR] as $severity) {
        if ($requirement = $this->createRequirementForSeverity($severity)) {
          $requirements["automatic_updates_status_$severity"] = $requirement;
        }
      }
    }
    return $requirements;
  }

  /**
   * Creates a requirement for checker results of a specific severity.
   *
   * @param int $severity
   *   The severity for requirement. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return mixed[]|null
   *   Requirements array as specified by hook_requirements(), or NULL
   *   if no requirements can be determined.
   */
  protected function createRequirementForSeverity(int $severity): ?array {
    $severity_messages = [];
    $results = $this->statusChecker->getResults($severity);
    if (!$results) {
      return NULL;
    }
    foreach ($results as $result) {
      $checker_messages = $result->getMessages();
      $summary = $result->getSummary();
      if (empty($summary)) {
        $severity_messages[] = ['#markup' => array_pop($checker_messages)];
      }
      else {
        $severity_messages[] = [
          '#type' => 'details',
          '#title' => $summary,
          '#open' => FALSE,
          'messages' => [
            '#theme' => 'item_list',
            '#items' => $checker_messages,
          ],
        ];
      }
    }
    $requirement = [
      'title' => $this->t('Update readiness checks'),
      'severity' => $severity,
      'value' => $this->getFailureMessageForSeverity($severity),
      'description' => [
        'messages' => [
          '#theme' => 'item_list',
          '#items' => $severity_messages,
        ],
      ],
    ];
    if ($run_link = $this->createRunLink()) {
      $requirement['description']['run_link'] = [
        '#type' => 'container',
        '#markup' => $run_link,
      ];
    }
    return $requirement;
  }

  /**
   * Creates a link to run the status checks.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A link, if the user has access to run the status checks, otherwise
   *   NULL.
   */
  protected function createRunLink(): ?TranslatableMarkup {
    $status_check_url = Url::fromRoute('automatic_updates.status_check');
    if ($status_check_url->access()) {
      return $this->t(
        '<a href=":link">Rerun readiness checks</a> now.',
        [':link' => $status_check_url->toString()]
      );
    }
    return NULL;
  }

}

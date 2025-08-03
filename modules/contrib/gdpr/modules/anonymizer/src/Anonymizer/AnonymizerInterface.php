<?php

namespace Drupal\anonymizer\Anonymizer;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * The GDPR Anonymizer Interface.
 *
 * @package Drupal\anonymizer\Anonymizer
 *  The anonymizer package.
 */
interface AnonymizerInterface {

  /**
   * Return an anonymized output.
   *
   * @var int|string $input
   *   The input.
   * @var \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field being anonymized.
   *
   * @return int|string
   *   The anonymized output.
   */
  public function anonymize($input, FieldItemListInterface $field = NULL);

}

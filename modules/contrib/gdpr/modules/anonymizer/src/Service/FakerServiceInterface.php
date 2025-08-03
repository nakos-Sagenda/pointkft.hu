<?php

namespace Drupal\anonymizer\Service;

/**
 * The GDPR Faker Service Interface.
 *
 * @package Drupal\anonymizer\Service
 *  The anonymizer package.
 */
interface FakerServiceInterface {

  /**
   * Return the generator.
   *
   * @return \Faker\Generator
   *   The generator.
   */
  public function generator();

}

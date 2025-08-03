<?php

/**
 * @file
 * Contains post-update hooks for Automatic Updates.
 */

declare(strict_types = 1);

use Drupal\automatic_updates\StatusCheckMailer;

/**
 * Creates the automatic_updates.settings:status_check_mail config.
 */
function automatic_updates_post_update_create_status_check_mail_config(): void {
  \Drupal::configFactory()
    ->getEditable('automatic_updates.settings')
    ->set('status_check_mail', StatusCheckMailer::ERRORS_ONLY)
    ->save();
}

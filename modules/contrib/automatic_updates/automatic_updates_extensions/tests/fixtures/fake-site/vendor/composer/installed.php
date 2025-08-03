<?php

/**
 * @file
 */

$projects_dir = __DIR__ . '/../../web/projects';
return [
  'versions' => [
    'drupal/my_module' => [
      'type' => 'drupal-module',
      'install_path' => $projects_dir . '/my_module',
    ],
    'drupal/contrib_profile1' => [
      'type' => 'drupal-profile',
      'install_path' => __DIR__ . '/../../web/profiles/contrib_profile1',
    ],
    'drupal/my_dev_module' => [
      'type' => 'drupal-module',
      'install_path' => $projects_dir . '/my_dev_module',
    ],
    'drupal/semver_test' => [
      'type' => 'drupal-module',
      'install_path' => $projects_dir . '/semver_test',
    ],
    'drupal/aaa_update_test' => [
      'type' => 'drupal-module',
      'install_path' => $projects_dir . '/aaa_update_test',
    ],
    'drupal/aaa_automatic_updates_test' => [
      'type' => 'drupal-module',
      'install_path' => $projects_dir . '/aaa_automatic_updates_test',
    ],
  ],
];

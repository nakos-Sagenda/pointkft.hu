<?php

/**
 * @file
 */

return [
  'root' => [
    'name' => '__root__',
    'pretty_version' => '1.2.4',
    'version' => '1.2.4.0',
    'reference' => NULL,
    'type' => 'library',
    'install_path' => __DIR__ . '/../../',
    'aliases' => [],
    'dev' => TRUE,
  ],
  'versions' => [
    '__root__' => [
      'pretty_version' => '1.2.4',
      'version' => '1.2.4.0',
      'reference' => NULL,
      'type' => 'library',
      'install_path' => __DIR__ . '/../../',
      'aliases' => [],
      'dev_requirement' => FALSE,
    ],
    'drupal/core' => [
      'pretty_version' => '9.8.0',
      'version' => '9.8.0.0',
      'reference' => '5aeab06c3087477e20e617328f2fa9f3ed18373d',
      'type' => 'drupal-core',
      'install_path' => __DIR__ . '/../drupal/core',
      'aliases' => [],
      'dev_requirement' => FALSE,
    ],
    'drupal/core-dev' => [
      'pretty_version' => '9.8.0',
      'version' => '9.8.0.0',
      'reference' => '6a8d7df3a5650a5d3bce6e478114064b176f7104',
      'type' => 'package',
      'install_path' => __DIR__ . '/../drupal/core-dev',
      'aliases' => [],
      'dev_requirement' => TRUE,
    ],
    'drupal/core-recommended' => [
      'pretty_version' => '9.8.0',
      'version' => '9.8.0.0',
      'reference' => 'c9babad9851edc2b7b4b43c778bc30db09f14946',
      'type' => 'project',
      'install_path' => __DIR__ . '/../drupal/core-recommended',
      'aliases' => [],
      'dev_requirement' => FALSE,
    ],
  ],
];

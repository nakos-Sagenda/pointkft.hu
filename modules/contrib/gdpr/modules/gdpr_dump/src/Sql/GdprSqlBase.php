<?php

namespace Drupal\gdpr_dump\Sql;

use Drupal\Core\Database\Database;
use Drush\Drush;
use Drush\Sql\SqlBase;
use function array_key_exists;
use function is_array;

/**
 * The GDPR GdprSqlBase.
 *
 * @see \Drush\Sql\SqlBase
 *
 * @package Drupal\gdpr_dump\Sql
 */
class GdprSqlBase extends SqlBase {

  /**
   * Get a driver specific instance of this class.
   *
   * @param mixed $options
   *   An options array as handed to a command callback.
   *
   * @return \Drush\Sql\SqlBase
   *   The instance.
   *
   * @throws \Exception
   */
  public static function create(array $options = []): ?SqlBase {
    // Set defaults in the unfortunate event that caller doesn't provide values.
    $options += [
      'database' => 'default',
      'target' => 'default',
      'db-url' => NULL,
      'databases' => NULL,
      'db-prefix' => NULL,
    ];
    $database = $options['database'];
    $target = $options['target'];

    if ($url = $options['db-url']) {
      $url = is_array($url) ? $url[$database] : $url;
      $dbSpec = self::dbSpecFromDbUrl($url);
      $dbSpec['db_prefix'] = $options['db-prefix'];
      return self::getInstance($dbSpec, $options);
    }
    if (($databases = $options['databases']) && array_key_exists($database, $databases) && array_key_exists($target, $databases[$database])) {
      // @todo 'databases' option is not declared anywhere?
      $dbSpec = $databases[$database][$target];
      return self::getInstance($dbSpec, $options);
    }
    if ($info = Database::getConnectionInfo($database)) {
      $dbSpec = $info[$target];
      return self::getInstance($dbSpec, $options);
    }

    throw new \RuntimeException(dt('Unable to load Drupal settings. Check your --root, --uri, etc.'));
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstance($dbSpec, $options): ?SqlBase {
    $driver = $dbSpec['driver'];
    $className = 'Drupal\gdpr_dump\Sql\GdprSql' . ucfirst($driver);

    // @todo Maybe add an interface, for now it's ok.
    /** @var \Drupal\gdpr_dump\Sql\GdprSqlBase $instance */
    // @todo Maybe use classResolver.
    $instance = new $className($dbSpec, $options);
    // Inject config.
    $instance->setConfig(Drush::service('config'));
    return $instance;
  }

}

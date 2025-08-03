<?php

namespace Drupal\gdpr_dump\Sql;

use Consolidation\SiteProcess\Util\Escape;
use Drupal\gdpr_dump\Form\SettingsForm;
use Drupal\gdpr_dump\Service\GdprSqlDump;
use Drush\Drush;
use Drush\Sql\SqlMysql;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function implode;
use function str_replace;
use function trim;

/**
 * The GDPR GdprSqlMysql.
 *
 * @package Drupal\gdpr_dump\Sql
 */
class GdprSqlMysql extends SqlMysql {

  /**
   * The config for gdpr dump.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $gdprDumpConfig;

  /**
   * An array of tables to skip.
   *
   * @var array
   */
  protected $tablesToSkip = [];

  /**
   * An array of tables to be anonymized.
   *
   * @var array
   */
  protected $tablesToAnonymize = [];

  /**
   * {@inheritdoc}
   */
  public function __construct($dbSpec, $options) {
    parent::__construct($dbSpec, $options);
    $this->gdprDumpConfig = \Drupal::config(SettingsForm::GDPR_DUMP_CONF_KEY);
    $this->tablesToAnonymize = $this->gdprDumpConfig->get('mapping') ?? [];
    $this->tablesToSkip = array_keys($this->gdprDumpConfig->get('empty_tables') ?? []);
  }

  /**
   * Execute a SQL dump and return the path to the resulting dump file.
   *
   * @return mixed
   *   Bool or void.
   */
  public function dump(): mixed {
    /** @var string|bool $file Path where dump file should be stored. If TRUE, generate a path based on usual backup directory and current date.*/
    $file = $this->getOption('result-file');
    $fileSuffix = '';
    $tableSelection = $this->getExpandedTableSelection($this->getOptions(), $this->listTables());
    $file = $this->dumpFile($file);
    $cmd = trim($this->dumpCmd($tableSelection));
    // Append the RENAME commands at the end.
    $renames = trim($this->createRenameCommands($tableSelection));

    if (!empty($renames)) {
      // @todo Cross-platform check.
      $cmd = '{ ' . $cmd . ' ; ' . $renames . ' }';
    }

    $pipefail = '';
    // Gzip the output from dump command(s) if requested.
    if ($this->getOption('gzip')) {
      // See https://github.com/drush-ops/drush/issues/3816.
      $pipefail = $this->getConfig()->get('sh.pipefail', 'bash -c "set -o pipefail; {{cmd}}"');
      $cmd .= ' | gzip -f';
      $fileSuffix .= '.gz';
    }

    if ($file) {
      $file .= $fileSuffix;
      $cmd .= ' > ' . Escape::shellArg($file);
    }

    $cmd = $this->addPipeFail($cmd, $pipefail);
    $process = Drush::shell($cmd, NULL, $this->getEnv());
    // Avoid the php memory of saving stdout.
    $process->disableOutput();
    // Show dump in real-time on stdout, for backward compat.
    $process->run($process->showRealtime());
    return $process->isSuccessful() ? $file : FALSE;
  }

  /**
   * Create table renames according to the GDPR config.
   *
   * @param array $tableSelection
   *   Supported keys: 'skip', 'structure', 'tables'.
   *
   * @return string
   *   The command.
   */
  protected function createRenameCommands(array $tableSelection): ?string {
    $skipTables = array_merge($tableSelection['skip'], $tableSelection['structure']);
    $skipTables = array_flip($skipTables);
    $skipTables += $this->tablesToSkip;

    $command = '';
    foreach (array_keys($this->tablesToAnonymize) as $table) {
      if (array_key_exists($table, $skipTables)) {
        // Don't try to rename a table if it is excluded.
        continue;
      }
      $clone = GdprSqlDump::GDPR_TABLE_PREFIX . $table;
      $rename = "RENAME TABLE \`$clone\` TO \`$table\`;";
      if (Drush::verbose() || $this->getConfig()->simulate()) {
        Drush::service('logger')->info("Adding rename command: '$rename'");
      }

      $command .= " ( echo \"$rename\" ); ";
    }

    return $command;
  }

  /**
   * Build bash for dumping a database.
   *
   * @return string
   *   One or more mysqldump/pg_dump/sqlite3/etc statements that are
   *   ready for executing. If multiple statements are needed,
   *   enclose in parenthesis.
   */
  public function dumpCmd($tableSelection): ?string {
    $multipleCommands = FALSE;
    $skipTables = $tableSelection['skip'];
    $structureTables = $tableSelection['structure'];
    $structureTables = array_merge($this->tablesToSkip, $structureTables);
    $tables = $tableSelection['tables'];

    $ignores = [];
    $skipTables = array_merge($structureTables, $skipTables);
    // Skip tables with sensitive data.
    $skipTables = array_merge(array_keys($this->tablesToAnonymize), $skipTables);
    $dataOnly = $this->getOption('data-only');
    // The ordered-dump option is only supported by MySQL for now.
    $orderedDump = $this->getOption('ordered-dump');

    $exec = 'mysqldump ';
    // Mysqldump wants 'databasename' instead of
    // 'database=databasename' for no good reason.
    $onlyDbName = str_replace('--database=', ' ', $this->creds());
    $exec .= $onlyDbName;

    // We had --skip-add-locks here for a while to help people with
    // insufficient permissions, but removed it because it slows down the
    // import a lot.  See http://drupal.org/node/1283978
    $extra = ' --no-autocommit --single-transaction --opt -Q';
    if ($dataOnly === TRUE) {
      $extra .= ' --no-create-info';
    }
    if ($orderedDump === TRUE) {
      $extra .= ' --skip-extended-insert --order-by-primary';
    }
    if ($option = $this->getOption('extra-dump')) {
      $extra .= " $option";
    }
    $exec .= $extra;

    if (!empty($tables)) {
      $exec .= ' ' . implode(' ', $tables);
    }
    else {
      // @todo Maybe use --ignore-table={db.table1,db.table2,...} syntax.
      // Append the ignore-table options.
      $dbSpec = $this->getDbSpec();
      foreach ($skipTables as $table) {
        $ignores[] = '--ignore-table=' . $dbSpec['database'] . '.' . $table;
        $multipleCommands = TRUE;
      }
      $exec .= ' ' . implode(' ', $ignores);

      // Run mysqldump again and append output
      // if we need some structure only tables.
      if (!empty($structureTables)) {
        $exec .= ' && mysqldump ' . $onlyDbName . " --no-data $extra " . implode(' ', $structureTables);
        $multipleCommands = TRUE;
      }
    }
    return $multipleCommands ? "($exec)" : $exec;
  }

}

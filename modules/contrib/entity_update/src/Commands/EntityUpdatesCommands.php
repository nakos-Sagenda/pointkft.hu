<?php

namespace Drupal\entity_update\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\entity_update\EntityCheck;
use Drupal\entity_update\EntityUpdate;
use Drupal\entity_update\EntityUpdatePrint;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush9 commands definitions.
 */
class EntityUpdatesCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * DevelEntityUpdatesCommands constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   *   The entity definition update manager.
   */
  public function __construct(ClassResolverInterface $class_resolver, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager) {
    parent::__construct();

    $this->classResolver = $class_resolver;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
  }

  /**
   * Apply pending entity schema updates.
   *
   * @param string $type
   *   The entity type ID to update.
   * @param array $options
   *   Array of options.
   *
   * @command entity:update
   * @aliases upe
   *
   * @option show Show entities to update
   * @option basic Update all entities as basic way
   * @option force Try force update
   * @option all Update all Entities
   * @option nobackup Disable automatic full database backup (Not recommended)
   * @option clean Cleanup entity backup database
   * @option bkpdel Copy entities to update in to backup database and delete
   *   entities
   * @option rescue Create entities from entity backup database
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller
   *   should then clear if needed.
   *
   * @usage drush upe --show
   *  Show entities to update
   * @usage drush upe --basic
   *  Update entities.Run this command if all entities to update are empty.
   *   Else Exception
   * @usage drush upe --all
   *  Update entities with data. Run this command if any entity (to update) has
   *   data
   * @usage drush upe --basic --nobackup
   *  Update all without automatic database backup
   * @usage drush upe --basic --force --nobackup
   *  Try to update using basic method even having data
   * @usage drush upe --all --nobackup
   *  Update all without automatic database backup (Not recommended)
   * @usage drush upe --clean
   *  Cleanup entity backup database
   * @usage drush upe --bkpdel
   *  Copy entities to update in to backup database and delete entities
   * @usage drush upe --rescue
   *  If entity recreation failed, You can you this option to create entities
   *   from entity backup database
   * @usage drush upe ENTITY_TYPE_ID --nobackup
   *  Update entity type ENTITY_TYPE_ID
   *
   * @bootstrap full
   *
   * @throws \Exception
   */
  public function entityUpdates($type = '', array $options = [
    'basic' => FALSE,
    'all' => FALSE,
    'bkpdel' => FALSE,
    'show' => FALSE,
    'clean' => FALSE,
    'rescue' => FALSE,
    'cache-clear' => FALSE,
    'nobackup' => TRUE,
  ]) {
    $caution_message = '
    - If you use this module, you are conscience what you are doing. You are the responsible of your work.
    - Please backup your database before any action.
    - Please Read the documentation before any action.
    - Do not use this module on multi sites.
    - Before a new update, Remove old backuped data if any (Using : drush upe --clean).
    ';

    $all = $options['all'];
    $basic = $options['basic'];
    $bkpdel = $options['bkpdel'];

    // Show entities to update.
    if ($options['show']) {
      EntityCheck::showEntityStatusCli();
      return;
    }

    // Clean entity backup database.
    elseif ($options['clean']) {
      EntityUpdate::cleanupEntityBackup();
      $this->io()->success("Entity backup data removed.");
      return;
    }

    // Restore all entities from database.
    elseif ($options['rescue']) {
      if ($this->io()->confirm('Are you sure you want create entities from backup database ? ')) {
        $res = EntityUpdate::entityUpdateDataRestore();
        $message = 'End of entities recreate process : ';
        $res ? $this->io()->success($message . ' ok') : $this->io()->error($message . ' error');

      }
      return;
    }

    // Check mandatory options.
    elseif (!$all && !$basic && !$bkpdel && empty($type)) {
      $this->io()->note('No option specified, please type "drush help upe" for help or refer to the documentation.');
      return;
    }

    // Caution message.
    $this->io()->caution($caution_message);

    // Backup database.
    if (!$options['nobackup']) {
      $db_backup_file = "backup_" . date("ymd-his") . ".sql.gz";
      $this->say("Backup database to : $db_backup_file");
      $this->say("To restore, run : gunzip < $db_backup_file | drush sqlc ");
      @exec('drush cr');
      @exec('drush sql-dump --gzip > ' . $db_backup_file);
    }

    // Basic entity update.
    elseif ($basic) {
      if ($this->io()->confirm('Are you sure you want update entities ?')) {
        $res = EntityUpdate::basicUpdate(!empty($options['force']));
        $message = 'Basic entities update : ';
        $res ? $this->io()->success($message . ' ok') : $this->io()->error($message . ' error');
      }
      return;
    }

    // Copy and delete entities.
    elseif ($bkpdel) {
      if ($this->io()->confirm('Are you sure you want copy entities to update in to backup database and delete entities ?')) {
        $res = EntityUpdate::entityUpdateDataBackupDel(EntityUpdate::getEntityTypesToUpdate($type), $type);
        $message = 'End of entities copy and delete process : ';
        $res ? $this->io()->success($message . ' ok') : $this->io()->error($message . ' error');
      }
      return;
    }

    // Update all entities.
    elseif ($all) {
      if (!empty($type)) {
        $this->io()->error("The option --all and a type has specified, please remove a one. : " . 'cancel');
        return;
      }

      elseif ($this->io()->confirm('Are you sure you want update all entities ?')) {
        $res = EntityUpdate::safeUpdateMain();
        $message = 'End of entities update process';
        $res ? $this->io()->success($message . ' ok') : $this->io()->error($message . ' error');
      }

      // cache-clear.
      if (!$options['cache-clear']) {
        $process = Drush::drush($this->siteAliasManager()
          ->getSelf(), 'cache-rebuild');
        $process->mustrun();
      }
      $this->io()->success(dt('Finished performing updates.'));
      return;
    }
    elseif ($type) {
      // Update a selected entity type.
      try {
        if ($entity_type = entity_update_get_entity_type($type)) {
          // Update the entity type.
          $res = EntityUpdate::safeUpdateMain($entity_type);
          $message = 'End of entities update process for : ' . $type;
          $res ? $this->io()->success($message . ' ok') : $this->io()->error($message . ' error');
          return;
        }
      }
      catch (Exception $e) {
        $this->io()->error($e->getMessage());
      }
      $this->io()->error("Entity type update Error : $type");
      return;
    }

  }

  /**
   * Check entity schema updates.
   *
   * @param string $type
   *   The entity type ID to update.
   * @param array $options
   *   Array of options.
   *
   * @command entity:check
   * @aliases upec
   *
   * @option types Show entities to update
   * @option list Show entities list
   * @option length Number of entities to show
   * @option start Start from
   *
   * @usage drush upec --show
   *
   * @usage drush upec node
   *  Show The entity summery.
   * @usage drush upec --types
   *  Show all entity types list.
   * @usage drush upec block --types
   *  Show all entity types list contains "block"
   * @usage drush upec node --list
   *  Show 10 entities.
   * @usage drush upec node --list --length=0
   *  Show all entities.
   * @usage drush upec node --list --start=2 --length=3
   *  Show 3 entities from 2.
   *
   * @bootstrap full
   *
   * @throws \Exception
   */
  public function entityCheck($type = '', array $options = [
    'start' => 0,
    'length' => 0,
    'show' => FALSE,
  ]) {
    // Options which hasn't need to have entity type.
    if (!empty($options['types'])) {
      // Display entity types list.
      EntityCheck::getEntityTypesList($type);
      return;
    }

    // Options need to have an entity type.
    elseif (!empty($type)) {

      if (!empty($options['list'])) {
        // Display entity types list.
        $length = !empty($options['length']) ?: 10;
        EntityCheck::getEntityList($type, !empty($options['start']), $length);
        return;
      }

      // Default action. Show the summary of the entity type.
      EntityUpdatePrint::displaySummary($type);
      return;
    }

    elseif ($options['show']) {
      EntityCheck::showEntityStatusCli();
      return;
    }

    $this->io()->note('No option specified, please type "drush help upec" for help or refer to the documentation.');

  }

}

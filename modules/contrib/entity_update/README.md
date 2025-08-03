# ENTITY UPDATE MODULE

The main objective of the module Entity Update is to allow module developers
and site administrators to update entity types schema even if entities have data.
The update can be executed by drush command (recommended), from web browser
or Programmatically.
The module also allows you to view entity types list, entity types update status
and show the contents of an entity type.


## CAUTION

- The entity update may damage your database, therefore backup the database
  before taking any action.
- For the production sites, please test twice on a non production site
  and put the site into maintain mode before any execution.
- If you plan to use this module, you should be conscious of what you are doing.
  You acknowledge that you are responsible for any issues due to this.

## Installation

Download via composer and install via drush (Recommended)
composer require drupal/entity_update
drush en entity_update -y

Download and install via drush
drush en entity_update -y

## SIMILAR MODULES

[Devel Entity Updates](https://www.drupal.org/project/devel_entity_updates)
This module is similar to 'Entity Update' module except:
'Entity Update' module allows:
- Updating fields of entities having data
- Update from web interface
- View changes on the web interface
- View entity data quickly

## ACTIONS AND DISPLAYS

No need to configure the module, but you can check Entities update status via:
- Administration -> Configuration -> Development -> Entity Update.
- Link : /admin/config/development/entity-update.

Permission : Administer software updates


## NEW IN VERSION 3.0

- The version 3 is compatible from drupal 9.4 and drupal 10.

### Version 3.0

- Update a selected entity type.
Use the web browser for:
- View entity types list.
- View entity schema update status.
- View entities list.
- Run Entity update (Not recommended for production sites, use Drush).

Test module

The module provide a test module entity_update_tests with configurable fields.
Configuration : /admin/config/development/entity-update/tests


## DOCUMENTATION

- Documentation Home : <https://www.drupal.org/docs/8/modules/entity-update>
- See Entity Update from drush
    <https://www.drupal.org/docs/8/modules/entity-update/entity-update-from-drush>
- See Entity Update usage from web browser.
    <https://www.drupal.org/docs/8/modules/entity-update/entity-update-usage-from-web-browser>
- See Update entities programmatically.
    <https://www.drupal.org/docs/8/modules/entity-update/update-entities-programmatically>
- See Usage in production sites.
    <https://www.drupal.org/docs/8/modules/entity-update/use-in-production-sites>

## USAGE EXAMPLES : entity-update (Via drush)

Drush command : entity-update
Alias : upe

1. Show Entities to update
drush upe --show

2. Update Entities basic way.
This method is does not work if any of the entity contains data.
drush upe --basic

3. Update All Entities.
drush upe --all

4. Update without automatic database backup
   Not recommended for --all, suitable for --basic
drush upe --basic --nobackup

5. Create entities from entity backup database.
   If entity recreation failed (on drush upe --all), You can use this option to
   re create entities from entity backup database.
drush upe --rescue

6. Cleanup entity backup database
drush upe --clean

7. Install New Entity type after module installation.
drush upe ENTITY_TYPE_ID -y

## USAGE EXAMPLES : entity-check (Via drush)

This command allow to show entities and entity types via drush.
Drush command : entity-check
Alias : upec

1. Show The summery of an entity type.
drush upec node

2. Show all entity types contains "block".
drush upec block --types

3. Show 3 entities from 2 of the type 'node'.
drush upec node --list --start=2 --length=3

## ADVANCE USE 

Update entity structure update or multiple operations
Developers Only - Not for Production sites.

If you want to change the structure of an entity type (Example : make non
translatable entity to a translatable) and your entity has data, you can try
the following steps.
CAUTION :
- You must structure the entity before starting development.
- This type of operations must not be done on production sites.

1. Cleanup the backup data table.
drush upe --clean
2. Backup data of your entity.
drush upe ENTITY_TYPE_ID --bkpdel
3. Update the code (Entity type definitions for example).
UPDATE YOUR ENTITY TYPE STEP BY STEP (See the doc).
4. Update the entity type (No need to backup full database again).
drush upe ENTITY_TYPE_ID --nobackup
-> Note : You can use 'gunzip < backup_XXX.sql.gz | drush sqlc' if necessary.
5. Create entities from entity backup database once every things are success.
drush upe --rescue
6. Cleanup the backup data table once every things are success.
drush upe --clean

User case 1. make translatable entity to a non translatable
1. Remove 'translatable = TRUE,'
2. update entity
3. Remove language key Ex : '"langcode" = "langcode",'
4. Update entity

User case 2. make non translatable entity to a translatable
1. Add 'translatable = TRUE,'
2. Add language key Ex : '"langcode" = "langcode",'
3. Update entity

## MAINTAINERS

- Nuwantha (iwsp) - <https://www.drupal.org/u/iwsp>
- NuWans - <https://www.drupal.org/u/nuwans>
- Siva Karthik - <https://www.drupal.org/u/sivakarthik229>

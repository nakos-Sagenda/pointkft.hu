#!/bin/bash

set -eo pipefail

# DXPR access token
if [ -z "$DXPR_ACCESS_TOKEN" ]
then
      echo "DXPR_ACCESS_TOKEN is empty"
      exit 1;
fi

# Configure the dxpr access token
composer config -g bearer.packages.dxpr.com $DXPR_ACCESS_TOKEN

# Creating a new project
composer create-project dxpr/lightning-dxpr-project:2.x-dev .

# Updating packages if using PHP ^8.0
if [[ "$PHP_TAG" =~ .*"8.0".* ]]; then
  composer update
fi

# Install the enterprise package
composer require 'dxpr/dxpr_builder_e:0.0.2'

# Create the settings.php file
chmod 755 docroot/sites/default/
cp docroot/sites/default/default.settings.php docroot/sites/default/settings.php && chmod 777 docroot/sites/default/settings.php
mkdir -p docroot/sites/default/files && chmod -R 777 docroot/sites/default/files

echo "Removing the dxpr builder module..."
rm -rf docroot/modules/contrib/dxpr_builder

echo "Linking to the dxpr builder module..."
ln -s $DXPR_BUILDER_CONTAINER docroot/modules/contrib/dxpr_builder

echo "Removing the dxpr theme module..."
rm -rf docroot/themes/contrib/dxpr_theme

echo "Linking to the dxpr theme module..."
ln -s $DXPR_THEME_CONTAINER docroot/themes/contrib/dxpr_theme

if [ -z ${NPM_INSTALL_STAMP+x} ]
then
      NPM_INSTALL_STAMP=".npm.installed"
fi
NPM_INSTALL_STAMP="$DXPR_BUILDER_CONTAINER/$NPM_INSTALL_STAMP"
echo "removing npm modules at $NPM_INSTALL_STAMP"
rm -rf "$NPM_INSTALL_STAMP" || true

# Installing DXPR QA demo website
drush site-install lightning_dxpr lightning_dxpr_demo_select.demo_select=$DXPR_DEMO --db-url=mysql://$DB_USER:$DB_PASSWORD@mariadb:3306/$DB_NAME --account-pass=$DXPR_ADMIN_PASSWORD -y -v

# Allow accessing website assets
chmod -R 777 docroot/sites/default/files

drush -y config-set --input-format=yaml dxpr_builder.settings json_web_token eyJhbGciOiJQUzI1NiIsImtpZCI6IjR6RGRXS1pGNGRfbXprcVVMc2tYb3ItcE96bGRITFN0WGI1Q1pUX3d4UnMifQ.eyJpc3MiOiJodHRwczpcL1wvZHhwci5jb20iLCJzdWIiOiIyMDAyNiIsImF1ZCI6Imh0dHBzOlwvXC9wYWNrYWdlcy5keHByLmNvbSIsInNjb3BlIjoiZHhwclwvZHhwcl9idWlsZGVyIiwiZHhwcl90aWVyIjoiYnVzaW5lc3MiLCJqdGkiOiIyZWU5NTAxZTMxMzc3NDIzOTAyNzMxMTdmZTFmZWIxMTUzOTA5NjEzMDJmZmQxY2U5YjIwMjBjZWM5YjUzNTIwIn0.BXnpSvUCeJl-yBIEpNkncAmTRgm-I9GD0bq0Tu_bH1cXGSNScmLLKEPxA5sndY4VKjQiKg9IN_sUuXZ1Xn3N3NC4ABofYkhOWCYzObh9MOleSGa-AuqYuig4ZAZNJSg-98Q1ULX98hVUSl1EcJx266EDF7cf49cC_AOmq7j9nKLDdaWvXydtTh6p6pYiXaeazw5rqYVnX7IKw9g2IJQ2PI79NjFPLgjtR-sUgEfDP72oaMbOPjBhrwPsmjFWWX2Gh8denebcDJuDGDC3LlGwHDVJ5yY0QCxShBHp_CS0LTkBZPNeubRlaoFhSQ0_R-NS_GDKQN1EYgKCc5tjfabzSb5IRd2vq0FwzyEaacIzpsPrV86KvOPWWm3YNHUGmtC241y5SSyl88DmUL4AppbFV9lL871DGsEBkdmJuN-VxTw3y0aM4-9h-_iBtsZXK9mxshGm3lIgi6BXfsG4nsQJmDt9lQ6jOwYQ87Vm_m73JIhtG3oypnXGw50DTKHCf6meXzcto4pympnV_M9kT-EfSZCAUHNttAI-jlf0KOkkoGZuVOCEDlXhZvPp19LsmDwN6jvKKcMX7MKMggdphbwIaJAGYhPPOaDy4uRQ7wKMg67Z4MSmW_7PnTOAMZ3OcrQGywaHhr7l8BYR8Z6dilBBZlk08EG4C4UK53vxuan9nh4

### Enable the DXPR analytics
if [ "$DXPR_RECORD_ANALYTICS" = true ] ; then
  echo "Enabling DXPR analytics..."
  drush -y config-set --input-format=yaml dxpr_builder.settings record_analytics true
else
  echo "Disabling DXPR analytics..."
  drush -y config-set --input-format=yaml dxpr_builder.settings record_analytics false
fi

### Enable the DXPR analytics
if [ "$DXPR_HIDE_REMINDERS" = true ] ; then
  echo "Enabling DXPR Hide Reminders..."
  drush -y config-set --input-format=yaml dxpr_builder.settings hide_reminders true
else
  echo "Disabling DXPR Hide Reminders..."
  drush -y config-set --input-format=yaml dxpr_builder.settings hide_reminders false
fi

### Disable the DXPR notifications
if [ "$DXPR_NOTIFICATIONS" = true ] ; then
  echo "Enabling DXPR notifications..."
  drush -y config-set --input-format=yaml dxpr_builder.settings notifications true
else
  echo "Disabling DXPR notifications..."
  drush -y config-set --input-format=yaml dxpr_builder.settings notifications false
fi

### Enable system messages so we fail visual tests on warnings.errors
drush config-set system.logging error_level verbose

# Load editor assets from local (minified) files.
drush -y config-set --input-format=yaml dxpr_builder.settings editor_assets_source 2
drush cr

# Remove the DXPR access token from the container composer config for security
composer config -g --unset bearer.packages.dxpr.com

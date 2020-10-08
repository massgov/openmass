#!/usr/bin/env bash

#This file is executed by the Drupal-Container. See https://github.com/massgov/Drupal-Container/blob/4459019c0cf7aa1bbd216e466f200ca3b8da12f2/dev/bin/apache2-foreground-enhanced#L12-L15
echo 'Start on-docker-boot.sh'
chown -R www-data:www-data docroot/sites/default/files docroot/sites/simpletest/browser_output
echo 'End on-docker-boot.sh'

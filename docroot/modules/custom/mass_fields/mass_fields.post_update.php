<?php

/**
 * @file
 * Run updates after updatedb has completed.
 */

use Drupal\Core\Database\Database;
use Drupal\devel_entity_updates\DevelEntityDefinitionUpdateManager;

/**
 * Updates a text format for field_rules_section_body to rules_of_court_section.
 */
function mass_fields_post_update_text_format() {
  Database::getConnection()
    ->update('paragraph__field_rules_section_body')
    ->fields(['field_rules_section_body_format' => 'rules_of_court_section'])
    ->execute();
}

/**
 * Entity schema update for new 'search' boolean field.
 */
function mass_fields_post_update_search_field() {
  $modules = ['devel', 'devel_entity_updates'];
  \Drupal::service('module_installer')->install($modules);
  Drupal::classResolver()
    ->getInstanceFromDefinition(DevelEntityDefinitionUpdateManager::class)
    ->applyUpdates();
  \Drupal::service('module_installer')->uninstall($modules);
}

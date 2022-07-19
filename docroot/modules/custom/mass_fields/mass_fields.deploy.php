<?php

use Drupal\devel_entity_updates\DevelEntityDefinitionUpdateManager;

function mass_fields_search_field() {
  Drupal::classResolver()
    ->getInstanceFromDefinition(DevelEntityDefinitionUpdateManager::class)
    ->applyUpdates();
}

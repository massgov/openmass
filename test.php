<?php

use Drupal\mayflower\Helper;

/**
 * Get all content type bundle names.
 */
function custom_module_get_entity_bundles($type) {
  $bundles = [];

  // Load the entity type manager service.
  $entity_type_manager = \Drupal::entityTypeManager();

  // Get a list of all content types.
  $content_types = $entity_type_manager->getStorage($type)->loadMultiple();

  // Iterate through content types to retrieve bundle names.
  foreach ($content_types as $content_type) {
    $bundles[] = $content_type->id();
  }

  return $bundles;
}

// Define a callback function to replace "node/[some numbers]" with dynamic values.
function replace_callback($matches) {
  $alias_manager = Drupal::service('path_alias.manager');

  // Load the alias for the node ID.
  $alias = $alias_manager->getAliasByPath($matches[0]);
  if ($alias) {
    return $alias;
  }
  else {
    return $matches[0];
  }
}

$entity_names = ['node_type', 'paragraphs_type'];
foreach ($entity_names as $entity_name) {
  if ($entity_name == 'node_type') {
    $entity_storage_name = 'node';
  }
  elseif ($entity_name == 'paragraphs_type') {
    $entity_storage_name = 'paragraph';
  }
  $types = custom_module_get_entity_bundles($entity_name);
  foreach ($types as $type) {
    $fields = Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_storage_name, $type);
    dump('========= start ==========');
    dump($type);
    foreach ($fields as $field_name => $definition) {
      switch ($definition->getType()) {
        case 'text_with_summary':
        case 'text_long':
          $query = Drupal::entityTypeManager()->getStorage($entity_storage_name)->getQuery();
          $query->condition('type', $type);
          // Add a condition to filter entities with a specific textarea field containing "node/nid".
          $query->condition("$field_name.value", 'node/', 'CONTAINS');

          // Execute the query and get a list of entity IDs.
          $entity_ids = $query->execute();
          if ($entity_ids) {
            $entities = Drupal::entityTypeManager()
              ->getStorage($entity_storage_name)
              ->loadMultiple($entity_ids);
            foreach ($entities as $entity) {
              if (Helper::isParagraphOrphan($entity)) {
                continue;
              }
              $list = $entity->get($field_name);
              foreach ($list as $delta => $item) {
                $values[$delta] = $item->getValue();
                $value = $item->getValue()['value'];
                $newString = preg_replace_callback('/\/node\/\d+/', 'replace_callback', $value);
                $values[$delta]['value'] = $newString;
              }
              if (method_exists($entity, 'setRevisionLogMessage')) {
                $entity->setNewRevision();
                $entity->setRevisionLogMessage('Revision created to fix plain node links.');
                $entity->setRevisionCreationTime(\Drupal::time()
                  ->getRequestTime());
              }
              $entity->set($field_name, $values);
              $entity->save();
              dump($entity->id());
              \Drupal::service('cache_tags.invalidator')->invalidateTags($entity->getCacheTagsToInvalidate());
            }

          }
          break;
      }
    }
    dump('========== end ===========');

  }
}

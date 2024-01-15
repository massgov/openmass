<?php

/**
 * Migrate from simple sitemap and metatag exclusions to the new boolean search fields.
 */
function mass_fields_deploy_search_fields() {
  $i = 0;
  $migrated = ['node' => [], 'media' => []];

  // Migrate from metatag.
  $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
  foreach ($types as $type) {
    $table = 'node__field_' . $type->id() . '_metatags';
    if (Drupal::database()->schema()->tableExists($table)) {
      $field = 'field_' . $type->id() . '_metatags_value';
      $query = \Drupal::database()->select($table, 'tab')
        ->fields('tab', ['entity_id', $field]);
      $or = $query->orConditionGroup();
      $or->condition($field, '%noindex%', 'LIKE');
      $or->condition($field, '%nosnippet%', 'LIKE');
      $result = $query->condition($or)->execute();
      $records = $result->fetchAllAssoc('entity_id');
      foreach ($records as $id => $columns) {
        $i++;
        if (in_array($id, $migrated['node'])) {
          // We already migrated this entity.
          continue;
        }
        elseif ($entity = Drupal::entityTypeManager()->getStorage('node')->load($id)) {
          $entity->set('search', str_contains($columns->$field, 'noindex'));
          $entity->set('search_nosnippet', str_contains($columns->$field, 'nosnippet'));
          $entity->save();
          \Drupal::logger('mass_fields')->notice('Migrated !url from metatag (!i of !total !type).', ['!url' => $entity->toUrl()->toString(), '!i' => $i, '!total' => count($records), '!type' => $type->id()]);
          $migrated['node'][] = $id;
        }
      }
      $i = 0;
    }
  }

  // Migrate from simplesitemap.
  $result = \Drupal::database()->select('simple_sitemap_entity_overrides', 'sseo')->fields('sseo', ['id', 'entity_type', 'entity_id'])->execute();
  $items = $result->fetchAllAssoc('id');
  $i = 0;
  foreach ($items as $item) {
    $i++;
    $entity = Drupal::entityTypeManager()->getStorage($item->entity_type)->load($item->entity_id);
    if ($entity && !in_array($entity->id(), $migrated['node'])) {
      $entity->set('search', TRUE)->save();
      \Drupal::logger('mass_fields')->notice('Migrated !url from simple sitemap (!i of !total).', ['!url' => $entity->toUrl()->toString(), '!i' => $i, '!total' => count($items)]);
      $migrated[$entity->getEntityTypeId()][] = $id;
    }
  }
  \Drupal::database()->truncate('simple_sitemap_entity_overrides');
}

<?php

/**
 * Migrate from simple sitemap and metatag exclusions to the new boolean 'search' field.
 */
function mass_fields_deploy_search_field3() {
  $i = 0;
  $migrated = [];

  // Migrate from metatag.
  $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
  foreach ($types as $type) {
    $table = 'node__field_' . $type->id() . '_metatags';
    if (Drupal::database()->schema()->tableExists($table)) {
      $result = \Drupal::database()->select($table, 'tab')
          ->fields('tab', ['entity_id'])
          ->condition('field_' . $type->id() . '_metatags_value', '%noindex%', 'LIKE')
          ->execute();
      $ids = $result->fetchCol();
      foreach ($ids as $id) {
        $i++;
        if (isset($migrated['node'][$id])) {
          // We already migrated this entity.
          continue;
        }
        elseif ($entity = Drupal::entityTypeManager()->getStorage('node')->load($id)) {
          $entity->set('search', TRUE)->save();
          \Drupal::logger('mass_fields')->notice('Migrated !url from metatag (!i of !total).', ['!url' => $entity->toUrl()->toString(), '!i' => $i, '!total' => count($ids)]);
          $migrated['node'][$id];
        }
      }
    }
  }

  // Migrate from simplesitemap.
  $result = \Drupal::database()->select('simple_sitemap_entity_overrides', 'sseo')->fields('sseo', ['id', 'entity_type', 'entity_id'])->execute();
  $items = $result->fetchAllAssoc('id');
  $i = 0;
  foreach ($items as $item) {
    $i++;
    $entity = Drupal::entityTypeManager()->getStorage($item->entity_type)->load($item->entity_id);
    if ($entity && !isset($migrated['node'][$entity->id()])) {
      $entity->set('search', TRUE)->save();
      \Drupal::logger('mass_fields')->notice('Migrated !url from simple sitemap (!i of !total).', ['!url' => $entity->toUrl()->toString(), '!i' => $i, '!total' => count($items)]);
    }
  }
  \Drupal::database()->truncate('simple_sitemap_entity_overrides');
}

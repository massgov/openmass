<?php

/**
 * Migrate from simple sitemap exclusion to the new boolean 'search' field.
 */
function mass_fields_deploy_search_field() {
  // Get all overrides.
  $result = \Drupal::database()->select('simple_sitemap_entity_overrides', 'sseo')->fields('sseo', ['id', 'entity_type', 'entity_id'])->execute();
  $items = $result->fetchAllAssoc('id');
  $i = 0;
  foreach ($items as $item) {
    $i++;
    if ($entity = Drupal::entityTypeManager()->getStorage($item->entity_type)->load($item->entity_id)) {
      $entity->set('search', TRUE)->save();
      \Drupal::logger('mass_fields')->notice('Migrated !url (!i of !total).', ['!url' => $entity->toUrl()->toString(), '!i' => $i, '!total' => count($items)]);
    }
  }
  \Drupal::database()->truncate('simple_sitemap_entity_overrides');
}

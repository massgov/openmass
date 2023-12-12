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

/**
 * Populate contextual search related field default values.
 */
function mass_fields_deploy_contextual_search_fields_default_values(&$sandbox) {

  $do_not_include_search_nids = [102151, 279281, 279391, 279421, 280801, 280841, 280891, 280911, 281166, 281201, 281276, 281411, 281456, 287771, 286861, 287811, 287841, 288221, 288231, 597581, 603051, 297131, 297136, 297146, 297166, 297176, 297226, 297236, 389236, 297506, 297546, 297716, 297726, 297741, 297761, 298191, 298201, 298261, 298271, 298291, 298511, 298516, 298616, 298791, 505031, 298881, 299621, 299616, 299511, 298531, 298536, 298541, 298546, 26951, 298551, 298556, 298561, 298571, 22331, 383086, 298581, 298586, 298591, 298211, 298216, 298221, 298226, 298231, 298236, 298241, 298246, 298251, 298256, 298186, 298006, 298011, 298026, 298031, 298036, 298041, 297791, 311511, 297801, 297811, 297806, 297816, 297826, 297831, 297836, 297841, 505026, 297671, 297666, 297661, 297651, 297631, 790306, 297616, 297601, 297591, 297301, 297446, 297441, 297431, 297421, 297416, 297411, 297401, 297396, 297246, 297391, 487206, 287871, 314251, 635851, 34161, 298566, 535211, 622806, 412921, 119191, 344641, 184756, 798351, 263216, 14066, 6096, 6516];
  $show_parent_nids = [705346, 408791, 790226, 342871, 514371, 415871, 685541, 468571, 22826, 51951, 418061, 64866, 571991, 646046, 790506, 14686, 683761, 13626, 47751, 528126, 15091, 74746, 629876, 609336, 71546, 144916, 71416, 74466, 6801, 511121, 13726, 409611, 507841, 74116, 13891, 205351, 27511, 488491];
  $nids_to_load = array_unique(array_merge($do_not_include_search_nids, $show_parent_nids));

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'org_page');
  $query->condition('nid', $nids_to_load, 'IN');

  if (empty($sandbox)) {
    // Get a list of all nodes of type org_page.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    try {
      if (in_array($node->id(), $do_not_include_search_nids)) {
        $node->set('field_org_no_search_filter', 1);
      }
      if (in_array($node->id(), $show_parent_nids)) {
        $node->set('field_include_parent_org_search', 1);
      }
      $node->setSyncing(TRUE);
      $node->save();
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }
    if (!$node->isLatestRevision()) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()->accessCheck(FALSE);
      $query->condition('nid', $node->id());
      $query->latestRevision();
      $rids = $query->execute();
      foreach ($rids as $rid) {
        $latest_revision = $storage->loadRevision($rid);
        if (isset($latest_revision)) {
          try {
            if (in_array($latest_revision->id(), $do_not_include_search_nids)) {
              $latest_revision->set('field_org_no_search_filter', 1);
            }
            if (in_array($latest_revision->id(), $show_parent_nids)) {
              $latest_revision->set('field_include_parent_org_search', 1);
            }
            $latest_revision->setSyncing(TRUE);
            $latest_revision->save();
          }
          catch (\Exception $e) {
            \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
          }
        }
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('Contextual search related field default values populated.');
  }
}

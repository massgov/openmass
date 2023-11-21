<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content.
 */

/**
 * Switch Image field data, form field_banner_image to field_bg_wide.
 */
function mass_content_deploy_org_page_banner_image_migration(&$sandbox) {
  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'org_page');

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

  $memory_cache = \Drupal::service('entity.memory_cache');

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    try {
      mass_content_org_node_banner_helper($node);
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
            mass_content_org_node_banner_helper($latest_revision);
          }
          catch (\Exception $e) {
            \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
          }
        }
      }
    }

    $sandbox['progress']++;
  }
  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('Org page Banner Image values has been populated.');
  }
}

/**
 * Helper function to populate Banner Image field values.
 */
function mass_content_org_node_banner_helper($node) {
  if (!$node->get('field_banner_image')->isEmpty() && $node->get('field_bg_wide')->isEmpty()) {
    $old_field_values = $node->get('field_banner_image')->getValue();
    $node->set('field_bg_wide', $old_field_values);
    $node->setSyncing(TRUE);
    $node->save();
  }
}

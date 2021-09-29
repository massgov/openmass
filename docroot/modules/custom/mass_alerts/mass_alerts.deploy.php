<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Alerts.
 */

use Drupal\node\Entity\Node;

/**
 * Implements hook_post_update_target_pages().
 *
 * Migrate Alert node to Sitewide Alerts.
 */
function mass_alerts_deploy_sitewide_alerts(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  if (!isset($sandbox['total'])) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'alert')
      ->condition('field_alert_display', 'site_wide')
      ->sort('nid')
      ->accessCheck(FALSE)
      ->execute();
    $sandbox['total'] = count($nids);
    $sandbox['current'] = 0;

    if (empty($sandbox['total'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $batch_size = 5;

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'alert')
    ->condition('field_alert_display', 'site_wide')
    ->range($sandbox['current'], $batch_size)
    ->sort('nid')
    ->accessCheck(FALSE)
    ->execute();

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $nodes = Node::loadMultiple($nids);

  // Loop through nodes in this batch.
  foreach ($nodes as $node) {

    $original_values = $node->toArray();
    $node->delete();
    $original_values['type'] = "sitewide_alert";

    // Remove nid and uuid, the cloned node is assigned new ones when saved.
    unset($original_values['nid']);
    unset($original_values['uuid']);

    // Remove revision data.
    // The latest revision becomes the only one in the new node.
    unset($original_values['vid']);
    unset($original_values['revision_translation_affected']);
    unset($original_values['revision_uid']);
    unset($original_values['revision_log']);
    unset($original_values['revision_timestamp']);

    $node_cloned = Node::create($original_values);
    $node_cloned->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = $sandbox['current'] >= $sandbox['total'] ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] >= 1) {
    return t('Migrated the data from Alert @nodes to Sitewide Alerts.', ["@nodes" => $sandbox['total']]);
  }
}

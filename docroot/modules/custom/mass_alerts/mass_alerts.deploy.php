<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Alerts.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

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

    $old_alert_ps = $original_values['field_alert'];
    unset($original_values['field_alert']);
    $new_paragraphs = [];
    foreach ($old_alert_ps as $old_alert_p) {
      $old_p = Paragraph::load($old_alert_p['target_id']);
      $new_p_values = [];
      $new_p_values['type'] = 'sitewide_alert_message';

      if (!empty($old_p->get('field_emergency_alert_content')->getValue())) {
        $new_p_values['field_sitewide_alert_content'] = $old_p->get('field_emergency_alert_content')->getValue();
      }

      if (!empty($old_p->get('field_emergency_alert_link')->getValue())) {
        $new_p_values['field_sitewide_alert_link'] = $old_p->get('field_emergency_alert_link')->getValue();
      }

      if (!empty($old_p->get('field_emergency_alert_link_type')->getValue())) {
        $new_p_values['field_sitewide_alert_link_type'] = $old_p->get('field_emergency_alert_link_type')->getValue();
      }

      if (!empty($old_p->get('field_emergency_alert_message')->getValue())) {
        $new_p_values['field_sitewide_alert_message'] = $old_p->get('field_emergency_alert_message')->getValue();
      }

      if (!empty($old_p->get('field_emergency_alert_timestamp')->getValue())) {
        $new_p_values['field_sitewide_alert_timestamp'] = $old_p->get('field_emergency_alert_timestamp')->getValue();
      }

      $new_p = Paragraph::create($new_p_values);
      $new_p->save();
      $new_paragraphs[] = [
        'target_id' => $new_p->id(),
        'target_revision_id' => $new_p->getRevisionId(),
      ];
    }

    $original_values['field_sitewide_alert'] = $new_paragraphs;

    $node_cloned = Node::create($original_values);
    $node_cloned->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = $sandbox['current'] >= $sandbox['total'] ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] >= 1) {
    return t('Migrated the data from Alert @nodes to Sitewide Alerts.', ["@nodes" => $sandbox['total']]);
  }
}

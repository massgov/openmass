<?php

/**
 * @file
 * Post update functions for Mass Feedback Form.
 */

use Drupal\node\Entity\Node;

/**
 * Set a default value for Constituent Communication options.
 */
function mass_feedback_form_post_update_constituent_communication(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'org_page');
  $query->notExists('field_constituent_communication');
  if (empty($sandbox)) {
    // Initialize other variables.
    $sandbox['current'] = 0;
    $sandbox['progress'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $entities = Node::loadMultiple($nids);

  // Give each node's "List Type" field a value.
  foreach ($entities as $entity) {
    $sandbox['current'] = $entity->id();
    $entity->set('field_constituent_communication', 'none');
    $entity->setNewRevision();
    $entity->setRevisionUserId(1);
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->setRevisionLogMessage('Programmatic update to set a default for Constituent communication options for each instance.');
    $entity->save();

    // Update the counter.
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Organization nodes have had a field_constituent_communication value assigned.');
  }
}

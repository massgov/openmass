<?php

/**
 * @file
 * Post update functions for Mass Content API.
 */

/**
 * Queue items to check for descendants.
 *
 * Nodes will be queued to update the descendant information when they are
 * updated, deleted, or added. This update will queue everything since this
 * functionality is new.
 */
function mass_content_api_post_update_descendant_populate() {
  $queue = \Drupal::queue('mass_content_api_descendant_queue');
  $descendant_manager = \Drupal::getContainer()->get('descendant_manager');

  $relationships = $descendant_manager->getRelationshipConfiguration();

  $query = \Drupal::entityQuery('node');
  $query->condition('status', 1)
    ->condition('type', array_keys($relationships), 'IN');

  $results = $query->execute();

  foreach ($results as $result) {
    $queue->createItem((object) ['id' => $result]);
  }
}

/**
 * Queue items to check for relationships.
 *
 * Nodes will be queued to update the relationship information when they are
 * updated, deleted, or added. This update will queue everything since this
 * functionality is new.
 */
function mass_content_api_post_update_relationship_populate() {
  $queue = \Drupal::queue('mass_content_api_relationship_queue');

  $query = \Drupal::entityQuery('node');
  $query->condition('status', 1);

  $results = $query->execute();

  foreach ($results as $result) {
    $queue->createItem((object) ['id' => $result]);
  }
}

/**
 * Populate descendant queue with all service pages.
 *
 * Because the offered by field was added to the descendant manager, these
 * parent / child relationships need to be regenerated.
 */
function mass_content_api_post_update_descendant_service_page_populate() {
  $queue = \Drupal::queue('mass_content_api_descendant_queue');

  $query = \Drupal::entityQuery('node');
  $query->condition('status', 1)
    ->condition('type', 'service_page');

  $results = $query->execute();

  foreach ($results as $result) {
    $queue->createItem((object) ['id' => $result]);
  }
}

/**
 * Add all items back to the relationship queue.
 *
 * Because the relationship schema was updated to allow for multiple
 * organization page parents, these need to be updated. Since this function
 * exists, it can be reused here.
 */
function mass_content_api_post_update_relationship_multi_org_populate() {
  $queue = \Drupal::queue('mass_content_api_relationship_queue');

  $query = \Drupal::entityQuery('node');
  $query->condition('status', 1);

  $results = $query->execute();

  foreach ($results as $result) {
    $queue->createItem((object) ['id' => $result, 'skip_children' => TRUE]);
  }
}

/**
 * Queue service and org pages after field update.
 *
 * Updates to the field configuration for org and service pages requires them
 * to be added to the queue for updating the parent / child and relationship
 * definitions.
 */
function mass_content_api_post_update_queue_service_and_org() {
  $dqueue = \Drupal::queue('mass_content_api_descendant_queue');
  $rqueue = \Drupal::queue('mass_content_api_relationship_queue');

  $bundles = ['service_page', 'org_page', 'decision_tree'];

  $query = \Drupal::entityQuery('node');
  $query->condition('status', 1)
    ->condition('type', $bundles, 'IN');

  $results = $query->execute();

  foreach ($results as $result) {
    $dqueue->createItem((object) ['id' => $result]);
    $rqueue->createItem((object) ['id' => $result]);
  }
}

/**
 * Queue nodes to be saved so the Linking Pages are populated.
 */
function mass_content_api_post_update_queue_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $nids = \Drupal::entityQuery('node')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  $relationship_queue = $queue_factory->get('mass_content_api_relationship_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  foreach ($nids as $nid) {
    $relationship_queue->createItem((object) ['id' => $nid]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes containing computed fields to be saved.
 *
 * Computed field configuration has been added to Linking Pages which requires
 * nodes containing these fields to be re-saved so the queues can process them.
 */
function mass_content_api_post_update_queue_computed_fields_nodes() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['advisory', 'decision', 'executive_order', 'rules', 'regulation'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes to be saved so the new DM table is populated.
 */
function mass_content_api_post_update_queue_nodes_for_save_2() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $nids = \Drupal::entityQuery('node')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  foreach ($nids as $nid) {
    $relationship_queue->createItem((object) ['id' => $nid]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes containing computed fields to be saved.
 *
 * Updating org content type with updated children and linking pages.
 */
function mass_content_api_post_update_queue_org_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['org_page'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes containing computed fields to be saved.
 *
 * Updating info details content type with linking pages.
 */
function mass_content_api_post_update_queue_info_details_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['info_details'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes containing computed fields to be saved.
 *
 * Updating binder content type with linking pages.
 */
function mass_content_api_post_update_queue_binder_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['binder'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes containing computed fields to be saved.
 *
 * Updating binder content type with linking pages.
 */
function mass_content_api_post_update_queue_info_details_nodes_for_save_2() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['info_details'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

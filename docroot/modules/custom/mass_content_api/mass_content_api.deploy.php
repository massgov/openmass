<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content API.
 */

/**
 * Queue campaign_landing nodes to be saved.
 *
 * Updating campaign_landing content type with linking pages.
 */
function mass_content_api_deploy_queue_campaign_landing_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['campaign_landing'];

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
  print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes to be saved.
 *
 * Updating alert, binder, curated_list, info_details,
 * service_page content type with linking pages.
 */
function mass_content_api_deploy_queue_contact_related_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = [
    'alert',
    'binder',
    'curated_list',
    'info_details',
    'service_page',
  ];

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
  print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

/**
 * Queue nodes to be saved.
 *
 * Updating info_details content type with linking pages.
 */
function mass_content_api_deploy_queue_info_details_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = [
    'info_details',
  ];

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
  print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}

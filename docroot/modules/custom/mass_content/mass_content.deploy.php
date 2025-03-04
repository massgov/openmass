<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content.
 */

use Drupal\Core\Url;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * Set focal point on the service page banner images.
 */
function mass_content_deploy_service_page_banner_image_focal_point(&$sandbox) {
  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'service_page');

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
      mass_content_banner_helper($node, 'service_page');
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
            mass_content_banner_helper($latest_revision, 'service_page');
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
    return t('Service page banner image focal points has been populated');
  }
}

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
      mass_content_banner_helper($node, 'org_page');
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
            mass_content_banner_helper($latest_revision, 'org_page');
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
function mass_content_banner_helper($node, string $content_type) {
  $changed = FALSE;
  if ($content_type == 'org_page') {
    if (!$node->get('field_banner_image')->isEmpty() && $node->get('field_bg_wide')->isEmpty()) {
      $old_field_values = $node->get('field_banner_image')->getValue();
      if (file_exists($node->get('field_banner_image')->entity->getFileUri())) {
        $old_field_values[0]['focal_point'] = '75,50';
        $node->set('field_bg_wide', $old_field_values);
        $changed = TRUE;
      }
    }
    if (!$node->get('field_bg_wide')->isEmpty()) {
      $field_values = $node->get('field_bg_wide')->getValue();
      if (file_exists($node->get('field_bg_wide')->entity->getFileUri())) {
        $field_values[0]['focal_point'] = '75,50';
        $node->set('field_bg_wide', $field_values);
        $changed = TRUE;
      }
    }
  }
  elseif ($content_type == 'service_page') {
    if (!$node->get('field_service_bg_wide')->isEmpty()) {
      $image = $node->get('field_service_bg_wide');
      if ($image->entity->getFileUri()) {
        if (file_exists($image->entity->getFileUri())) {
          $field_values = $image->getValue();
          $field_values[0]['focal_point'] = '75,50';
          $node->set('field_service_bg_wide', $field_values);
          $changed = TRUE;
        }
      }
    }
  }
  if ($changed) {
    $node->setSyncing(TRUE);
    $node->save();
  }
}

/**
 * Migrate Card paragraph link field label into the Card header text field.
 */
function mass_content_deploy_card_label_migration(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('paragraph')->accessCheck(FALSE);
  $query->condition('type', 'card');

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

  $paragraphs = $paragraph_storage->loadMultiple($pids);

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($paragraphs as $paragraph) {
    $sandbox['current'] = $paragraph->id();
    try {
      if (!Helper::isParagraphOrphan($paragraph)) {
        if (!$paragraph->get('field_card_link')->isEmpty()) {
          $title = $paragraph->get('field_card_link')->title;
          if (!$title) {
            $uri = $paragraph->get('field_card_link')->uri;
            $url = Url::fromUri($uri);
            if (!$url->isExternal() && $url->getRouteName() == 'entity.node.canonical') {
              $route_params = $url->getRouteParameters();
              if ($route_params['node']) {
                if ($node = Node::load($route_params['node'])) {
                  $title = $node->getTitle();
                }
              }
            }
          }
          $paragraph->set('field_card_header', $title);
          $paragraph->save();
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('Card paragraph link field label into the Card header text field migration has been completed.');
  }
}

/**
 * Deploy hook to update field_form_platform to 'Formstack' for all form_page content type nodes.
 */
function mass_content_deploy_form_platform_update(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'form_page');

  // Initialize sandbox if this is the first run.
  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count_query = clone $query;
    $sandbox['max'] = $count_query->count()->execute();
  }

  // Set batch size to avoid memory exhaustion.
  $batch_size = 50;

  // Fetch node IDs in batches.
  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  // Load the nodes from the fetched node IDs.
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  // Temporarily disable entity hierarchy writes to avoid issues during batch processing.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($nodes as $node) {
    // Update the sandbox current nid.
    $sandbox['current'] = $node->id();
    try {
      // Check if the field exists and is not already set to 'formstack'.
      if ($node->hasField('field_form_platform')) {
        // Set the field value to 'formstack'.
        $node->set('field_form_platform', 'formstack');
        // Save the node.
        $node->save();
      }
    }
    catch (\Exception $e) {
      // Catch any exception and continue processing.
      \Drupal::logger('mass_validation')->error('Failed to update node @nid: @message', [
        '@nid' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      // Log the error but do not re-enable writes here, continue processing.
    }

    // Track progress.
    $sandbox['progress']++;
  }

  // Log progress for each batch.
  \Drupal::logger('mass_validation')->notice('Processed @progress out of @max nodes.', [
    '@progress' => $sandbox['progress'],
    '@max' => $sandbox['max'],
  ]);

  // Update finished state.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Re-enable entity hierarchy writes after all nodes are processed.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('All form_page nodes have been updated with the "Formstack" value in field_form_platform.');
  }
}

/**
 * Deploy hook to update field_tableau_embed_type to 'default' for all tableau_embed paragraphs.
 */
function mass_content_deploy_tableau_embed_type_update(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('paragraph')->accessCheck(FALSE);
  $query->condition('type', 'tableau_embed');

  // Initialize sandbox if this is the first run.
  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count_query = clone $query;
    $sandbox['max'] = $count_query->count()->execute();
  }

  // Set batch size to avoid memory exhaustion.
  $batch_size = 50;

  // Fetch paragraph IDs in batches.
  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  // Load the paragraphs from the fetched IDs.
  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $paragraphs = $paragraph_storage->loadMultiple($pids);

  // Temporarily disable entity hierarchy writes to avoid issues during batch processing.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($paragraphs as $paragraph) {
    // Update the sandbox current pid.
    $sandbox['current'] = $paragraph->id();
    try {
      // Check if the field exists and is not already set.
      if ($paragraph->hasField('field_tableau_embed_type') && !$paragraph->get('field_tableau_embed_type')->value) {
        // Set the field value to 'default'.
        $paragraph->set('field_tableau_embed_type', 'default');
        // Save the paragraph.
        $paragraph->save();
      }
    }
    catch (\Exception $e) {
      // Catch any exception and continue processing.
      \Drupal::logger('mass_content')
        ->error('Failed to update paragraph @pid: @message', [
          '@pid' => $paragraph->id(),
          '@message' => $e->getMessage(),
        ]);
    }

    // Track progress.
    $sandbox['progress']++;
  }

  // Log progress for each batch.
  \Drupal::logger('mass_content')
    ->notice('Processed @progress out of @max paragraphs.', [
      '@progress' => $sandbox['progress'],
      '@max' => $sandbox['max'],
    ]);

  // Update finished state.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Re-enable entity hierarchy writes after all paragraphs are processed.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('All tableau_embed paragraphs have been updated with the "default" value in field_tableau_embed_type and reflected in the referencing nodes.');
  }
}

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

///**
// * Migrate section_long_form paragraphs to the new layout structure in info_details nodes.
// */
//function mass_content_deploy_info_details_migration(&$sandbox) {
//  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
//
//  // Disable entity hierarchy writes for better performance during processing.
//  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);
//
//  // Query all nodes of type 'info_details'.
//  $query = \Drupal::entityQuery('node')
//    ->condition('type', 'info_details')
//    ->accessCheck(FALSE);
//
//  if (empty($sandbox)) {
//    $sandbox['progress'] = 0;
//    $sandbox['current'] = 0;
//    $count = clone $query;
//    $sandbox['max'] = $count->count()->execute();
//    \Drupal::logger('mass_content_deploy')->notice('Starting to process info_details nodes. Total nodes to process: @max', ['@max' => $sandbox['max']]);
//  }
//
//  $batch_size = 150;
//  $nids = $query->condition('nid', $sandbox['current'], '>')
//    ->sort('nid')
//    ->range(0, $batch_size)
//    ->execute();
//
//  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
//  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
//
//  // Load nodes in batches.
//  $nodes = $node_storage->loadMultiple($nids);
//
//  foreach ($nodes as $node) {
//    $sandbox['current'] = $node->id();
//
//    \Drupal::logger('mass_content_deploy')->info('Processing node @nid (@progress / @max)', [
//      '@nid' => $node->id(),
//      '@progress' => $sandbox['progress'] + 1,
//      '@max' => $sandbox['max'],
//    ]);
//
//    if ($node->hasField('field_info_details_sections')) {
//      $sections = $node->get('field_info_details_sections')->referencedEntities();
//      $new_sections = [];
//
//      foreach ($sections as $section) {
//        if ($section->bundle() === 'section_long_form') {
//          // Check if heading is not hidden.
//          if (!$section->get('field_hide_heading')->value) {
//            // Create a new 'section_header' paragraph for the heading.
//            $section_header_paragraph = $paragraph_storage->create([
//              'type' => 'section_header',
//              'field_section_long_form_heading' => $section->get('field_section_long_form_heading')->value,
//            ]);
//            $section_header_paragraph->save();
//            $new_sections[] = [
//              'target_id' => $section_header_paragraph->id(),
//              'target_revision_id' => $section_header_paragraph->getRevisionId(),
//            ];
//          }
//
//          // Migrate each content paragraph in field_section_long_form_content.
//          $content_paragraphs = $section->get('field_section_long_form_content')->referencedEntities();
//          foreach ($content_paragraphs as $content_paragraph) {
//            $new_sections[] = [
//              'target_id' => $content_paragraph->id(),
//              'target_revision_id' => $content_paragraph->getRevisionId(),
//            ];
//          }
//        }
//      }
//
//      // Update the node with the newly structured paragraphs.
//      $node->set('field_info_details_sections', $new_sections);
//      if (method_exists($node, 'setRevisionLogMessage')) {
//        $node->setNewRevision();
//        $node->setRevisionLogMessage('Revision created for layout paragraphs.');
//        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
//      }
//      $node->save();
//
//      \Drupal::logger('mass_content_deploy')->info('Node @nid processed successfully.', ['@nid' => $node->id()]);
//    }
//
//    $sandbox['progress']++;
//  }
//
//  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
//  if ($sandbox['#finished'] >= 1) {
//    // Re-enable entity hierarchy writes after processing.
//    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
//    \Drupal::logger('mass_content_deploy')->notice('Processing info_details nodes completed. Processed @max nodes.', ['@max' => $sandbox['max']]);
//    return t('Migration of info_details nodes to the new layout structure has been completed.');
//  }
//}

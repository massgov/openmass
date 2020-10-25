<?php

/**
 * @file
 * Implementations of hook_post_update_NAME() for Mass Alerts.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements hook_post_update_target_pages().
 *
 * Migrate target page references from paragraph to field.
 */
function mass_alerts_post_update_target_pages(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  if (!isset($sandbox['total'])) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'alert')
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

    // This flag is for appending multiple items and saving the node just once.
    $has_target_id = FALSE;

    /** @var Drupal\node\Entity\Node $node */
    if ($node->hasField('field_target_pages_para_ref') && $node->hasField('field_target_page')) {

      // Loop through each paragraph reference in the
      // field_target_pages_para_ref field.
      foreach ($node->get('field_target_pages_para_ref') as $paragraph_ref) {
        if (isset($paragraph_ref->target_id)) {

          // Load each paragraph and get the node_id it references.
          /** @var \Drupal\Paragraphs\ParagraphInterface $paragraph */
          $paragraph = Paragraph::load($paragraph_ref->target_id);
          $target_node_id = reset($paragraph->get('field_target_content_ref')->getValue());

          if ($target_node_id) {
            // Prevent duplicates being copied into new field_target_page field
            // by looking at the field to verify it is not already in there.
            $already_referenced = FALSE;
            foreach ($node->get('field_target_page')->referencedEntities() as $item) {
              if ($item->id() == $target_node_id['target_id']) {
                $already_referenced = TRUE;
              }
            }
            if (!$already_referenced) {
              $node->get('field_target_page')->appendItem($target_node_id['target_id']);
              $has_target_id = TRUE;
            }
          }
        }
      }
      if ($has_target_id) {
        $node->save();
      }
    }
    $sandbox['current']++;
  }

  $sandbox['#finished'] = $sandbox['current'] >= $sandbox['total'] ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] >= 1) {
    return t('Migrated the data from field_target_pages_para_ref to field_target_page for @nodes Alert nodes.', ["@nodes" => $sandbox['total']]);
  }

  /**
   * Implements hook_post_update_NAME.
   *
   * Empty unused paragraphs.
   */
  function mass_alerts_post_update_paragraphs_target_pages(&$sandbox) {
    $entityTypeManager = Drupal::entityTypeManager();
    $storage = $entityTypeManager->getStorage('paragraph');
    $query = $storage->getQuery();
    $bundleKey = $entityTypeManager->getDefinition('paragraph')->getKey('bundle');
    $query = $query->condition($bundleKey, 'target_pages');
    $result = $query->execute();
    $entities = $storage->loadMultiple($result);
    $storage->delete($entities);
  }

}

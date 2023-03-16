<?php

/**
 * Query media and track usage in the autamatic list of curated list.
 */
function mass_media_deploy_update_documents_usage(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('media')->accessCheck(FALSE);
  $query->condition('bundle', 'document');
  $query->condition('field_document_label', '', 'IS NOT NULL');

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 5000;

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  $mids = $query->condition('mid', $sandbox['current'], '>')
    ->sort('mid')
    ->range(0, $batch_size)
    ->execute();

  $media_storage = \Drupal::entityTypeManager()->getStorage('media');

  $documents = $media_storage->loadMultiple($mids);

  foreach ($documents as $document) {
    $sandbox['current'] = $document->id();
    try {
      // This helps the system to track documents in the automatic curated lists.
      if ($labels = $document->field_document_label->getValue()) {
        $total = Drupal::service('entity_usage.usage')->listUniqueSourcesCount($document);
        if (!$total) {
          foreach ($labels as $label) {
            // Check if there are paragraphs with the same labels.
            // If there are, this means we should track the document.
            $paragraphs = \Drupal::entityTypeManager()
              ->getStorage('paragraph')
              ->loadByProperties([
                'field_listdynamic_label' => $label['target_id'],
              ]);
            if ($paragraphs) {
              foreach ($paragraphs as $paragraph) {
                if (\Drupal::config('entity_usage_queue_tracking.settings')
                  ->get('queue_tracking')) {
                  \Drupal::queue('entity_usage_tracker')->createItem([
                    'operation' => 'update',
                    'entity_type' => 'paragraph',
                    'entity_id' => $paragraph->id(),
                  ]);
                }
              }
            }
          }
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
    return t('Processed @total media items to track usage.', ['@total' => $sandbox['progress']]);
  }
  return "Processed {$sandbox['progress']} items.";
}

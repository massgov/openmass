<?php

namespace Drupal\mass_content;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;

/**
 * Manages Mass Content batch processing.
 */
class MassContentBatchManager {

  /**
   * Process the node to migrate date field values.
   */
  public function processNode($id, ContentEntityBase $node, $operation_details, &$context) {
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    // Turn off entity_hierarchy writes while processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

    $memory_cache = \Drupal::service('entity.memory_cache');
    // Sets a mapping of content type to "date" fields.
    $date_fields = [
      'binder' => 'field_binder_date_published',
      'decision' => 'field_decision_date',
      'executive_order' => 'field_executive_order_date',
      'info_details' => 'field_info_details_date_publishe',
      'regulation' => 'field_regulation_last_updated',
      'rules' => 'field_rules_effective_date',
      'advisory' => 'field_advisory_date',
      'news' => 'field_news_date',
    ];

    try {
      if (in_array($node->bundle(), array_keys($date_fields))) {
        $field_name = $date_fields[$node->bundle()];
        if ($node->hasField($field_name) && $node->hasField('field_date_published')) {
          if (!$node->$field_name->isEmpty()) {

            $published_date = $node->get($field_name)->getValue();
            if ($field_name == 'field_news_date') {
              $news_date = $node->get($field_name)->getValue()[0]['value'];
              $user_timezone = 'America/New_York';
              if (!empty($node->getOwner())) {
                $user_timezone = $node->getOwner()->getTimeZone();
              }
              $date_original = new DrupalDateTime($news_date, 'UTC');
              $date_original->setTimezone(timezone_open($user_timezone));
              $converted_date = $date_original->format('Y-m-d');
              $published_date = explode('T', $converted_date)[0];

            }
            $node->set($field_name, NULL);
            $node->set('field_date_published', $published_date);
            // Save the node.
            // Save without updating the last modified date. This requires a core patch
            // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
            $node->setSyncing(TRUE);
            $node->save();
            $memory_cache->deleteAll();
            // Turn on entity_hierarchy writes after processing the item.
            \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }

    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $id;

    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details',
      ['@id' => $id, '@details' => $operation_details]
    );
  }

  /**
   * Process the service_page node to migrate data.
   */
  public static function processServiceNode($id, ContentEntityBase $node, array $service_with_events, $operation_details, &$context) {
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    // Turn off entity_hierarchy writes while processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

    // Include migration functions.
    require_once __DIR__ . '/../includes/mass_content.service_page.inc';

    try {

      $template = $node->field_template->value;

      _mass_content_service_page_add_social_links($node);
      switch ($template) {
        case 'custom':
          _mass_content_service_page_migration_custom_link_group($node);
          break;

        case 'default':
          _mass_content_service_page_migration_default_link_group($node);
          break;

      }
      _mass_content_service_page_add_contact_information($node);
      _mass_content_service_page_migration_locations($node);
      if (in_array($node->id(), $service_with_events)) {
        _mass_content_service_page_add_event_section($node);
      }
      if ($template !== 'custom') {
        _mass_content_service_page_migrate_additional_resources($node);
      }
      _mass_content_service_page_cleanup_field_values($node);

      // Save the node.
      // Save without updating the last modified date. This requires a core patch
      // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
      $node->setSyncing(TRUE);
      $node->save();
      // Turn on entity_hierarchy writes after processing the item.
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }

    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $id;

    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details',
      ['@id' => $id, '@details' => $operation_details]
    );
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function processNodeFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}

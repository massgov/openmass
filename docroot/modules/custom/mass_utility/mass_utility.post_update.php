<?php

/**
 * @file
 * Run updates after updatedb has completed.
 */

use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Component\Utility\Unicode;
use Drupal\redirect\Entity\Redirect;
use Drupal\media\Entity\Media;

/**
 * Populate new address field.
 *
 * See https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_post_update_NAME/8.3.x.
 */
function mass_utility_post_update_address_field() {
  $eq = \Drupal::entityQuery('node');
  $nids = $eq->condition('type', 'contact_information')->execute();
  /** @var Drupal\node\Entity\Node[] $nodes */
  $nodes = Node::loadMultiple($nids);
  foreach ($nodes as $node) {
    // @todo Parse local CSV file.
    $value = 'foo';
    // $node->field_address_address->addressLine1->setValue($value);
    // $node->save();
  }
}

/**
 * Migrate field data from old string fields to formatted text fields.
 *
 * Field data must be copied from the deleted field tables to the freshly
 * created ones that include a 'format' column.
 *
 * @see mass_utility_update_7014()
 */
function mass_utility_post_update_location_text_fields() {
  $field_names = [
    'field_parking',
    'field_restrictions',
    'field_accessibility',
    'field_services',
  ];
  foreach ($field_names as $field_name) {
    $from_instances = \Drupal::service('entity_type.manager')
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => 'node',
        'bundle' => 'location',
        'field_name' => $field_name,
        'deleted' => 1,
      ]);
    foreach ($from_instances as $deleted_instance) {
      $mapping = \Drupal::service('entity_type.manager')->getStorage('node')->getTableMapping();

      // Gather data about the destination (node__field_X).
      $to_instance = FieldConfig::load('node.location.' . $field_name);
      $to_storage = $to_instance->getFieldStorageDefinition();
      $to_base_table = $mapping->getDedicatedDataTableName($to_storage);
      $to_revision_table = $mapping->getDedicatedRevisionTableName($to_storage);

      // Gather data about the source (field_deleted_field_X).
      $deleted_storage = $deleted_instance->getFieldStorageDefinition();
      $deleted_base_table = $mapping->getDedicatedDataTableName($deleted_storage, TRUE);
      $deleted_revision_table = $mapping->getDedicatedRevisionTableName($deleted_storage, TRUE);

      // Gather data about the columns that must be migrated.
      $format_column = $mapping->getFieldColumnName($to_storage, 'format');
      // Don't try to migrate the format or deleted columns.
      $base_columns = array_diff($mapping->getAllColumns($to_base_table), [$format_column, 'deleted']);
      $revision_copy_columns = array_diff($mapping->getAllColumns($to_revision_table), [$format_column, 'deleted']);

      // Copy base table data (field_deleted_data_X -> node__field_X)
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      $base_table_select = \Drupal::database()->select($deleted_base_table, 's')
        ->fields('s', $base_columns);
      $base_table_select->addExpression("'basic_html'", $format_column);
      $base_table_select->addExpression('0', 'deleted');
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      \Drupal::database()->insert($to_base_table)
        ->from($base_table_select)
        ->execute();

      // Copy revision table data
      // (field_deleted_revision_X -> node_revision__field_X)
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      $revision_table_select = \Drupal::database()->select($deleted_revision_table, 's')
        ->fields('s', $revision_copy_columns);
      // Default text format to basic_html.
      $revision_table_select->addExpression("'basic_html'", $format_column);
      $revision_table_select->addExpression('0', 'deleted');
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      \Drupal::database()->insert($to_revision_table)
        ->from($revision_table_select)
        ->execute();
    }
  }
}

/**
 * DP-6012: Add Adjustment Type vocabulary terms.
 */
function mass_utility_post_update_add_vocabulary_terms() {
  // Machine name of the Taxonomy vocabulary.
  $vocab = 'adjustment_type';

  // Term names to be added.
  $items = [
    'Rescinding',
    'Revoking',
    'Superseding',
    'Revoking and Superseding',
    'Amending',
  ];
  foreach ($items as $name) {
    Term::create([
      'parent' => [],
      'name' => $name,
      'vid' => $vocab,
    ])->save();
  }
}

/**
 * DP-6012: Migrates Adjustment Type paragraph field data between fields.
 */
function mass_utility_post_update_migrate_adjustment_type() {
  // Load the Adjustment Type paragraphs to be changed.
  $pararaphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['type' => ['adjustment_type']]);
  // Load the newly created Adjustment Type vocabulary, which is replacing the
  // Adjustment Type text list on the Adjustment Type paragraph bundle.
  $adjustment_type_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('adjustment_type');
  foreach ($adjustment_type_terms as $term) {
    $adjustment_term_map[strtolower($term->name)] = $term->tid;
  }
  // In the text field of the Adjustment Type paragraph bundle,
  // field_adjustment_type has a misspelled key for "Superseding". To match
  // the key to the correctly spelled vocabulary, this manually sets the mapping
  // to the misspelled variant.
  if (isset($adjustment_term_map['superseding'])) {
    $adjustment_term_map['superceding'] = $adjustment_term_map['superseding'];
    unset($adjustment_term_map['superseding']);
  }
  foreach ($pararaphs as $paragraph) {
    if (!$paragraph->hasField('field_adjustment_type_term')) {
      throw new Exception("field_adjustment_type_link has not yet been added to the Adjustment Type paragraph entity. This should be resolved once configuration is imported and updates are attempted again.");
    }
    if (!$paragraph->field_adjustment_type->isEmpty()) {
      $paragraph->field_adjustment_type_term
        ->setValue($adjustment_term_map[$paragraph->field_adjustment_type->value]);
      $paragraph->save();
    }
  }
}

/**
 * Updates Curated List nodes that have manual lists.
 *
 * Multi-value list items become multiple manual lists instead.
 */
function mass_utility_post_update_curated_list_manual_sections(&$sandbox = NULL) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['current_index'] = 0;
    $sandbox['max'] = \Drupal::entityQuery('node')
      ->condition('type', 'curated_list')
      ->count()
      ->execute();
  }

  $batch_size = 50;
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'curated_list')
    ->condition('nid', $sandbox['current_index'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $result = Node::loadMultiple($nids);
  foreach ($result as $node) {
    $sandbox['current_index'] = $node->id();
    /** @var Drupal\node\Entity\Node $node */
    $list_items = $node->get('field_curatedlist_list_section')->referencedEntities();
    foreach ($list_items as $list_index => $list_item) {
      $list_item_type = $list_item->get('type')->first()->getValue()['target_id'];
      // If manual list section.
      if ($list_item_type == 'list_static') {
        $link_references = $list_item->get('field_liststatic_items')->referencedEntities();
        $field_liststatic_items = $list_item->get('field_liststatic_items')->getValue();
        $new_field_liststatic_items = [];
        foreach ($link_references as $link_index => $link_reference) {
          $link_reference_type = $link_reference->get('type')->first()->getValue()['target_id'];
          if ($link_reference_type == 'list_item_document') {
            $new_field_liststatic_items[] = [
              'target_id' => $link_reference->id(),
              'target_revision_id' => $link_reference->getRevisionId(),
            ];
          }
          elseif ($link_reference_type == 'list_item_link' && $link_reference->get('field_listitemlink_item')->count() > 1) {
            $links = $link_reference->get('field_listitemlink_item')->getValue();
            // Looping through multi-value list item here.
            // For each link, create a new list_item_link paragraph, then add the reference to list_item.
            foreach ($links as $link) {
              $new_item_link = Paragraph::create([
                'type' => 'list_item_link',
              ]);
              $new_item_link->set('field_listitemlink_item', $link);
              $new_item_link->save();
              // Set the new item link id to the node.
              $new_field_liststatic_items[] = [
                'target_id' => $new_item_link->id(),
                'target_revision_id' => $new_item_link->getRevisionId(),
              ];
            }
          }
          else {
            // Manual list sections with only one entry or less get preserved.
            $new_field_liststatic_items[] = [
              'target_id' => $link_reference->id(),
              'target_revision_id' => $link_reference->getRevisionId(),
            ];
          }
        }
        // If the new list static array has changed, save.
        if (!empty($new_field_liststatic_items) && $new_field_liststatic_items != $field_liststatic_items) {
          $list_item->set('field_liststatic_items', $new_field_liststatic_items);
          $list_item->save();
        }
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
}

/**
 * Helper function to truncate and copy data from source field to a target field.
 *
 * @param Drupal\node\Entity\Node $node
 *   The node object to copy and update from.
 * @param string $source_field
 *   The name of the source field.
 * @param string $target_field
 *   The name of the target field.
 *
 * @throws \Exception
 */
function mass_utility_listing_description_set_value(Node $node, $source_field, $target_field) {
  $source = $node->$source_field->getValue();
  if (!empty($source)) {
    $text = strip_tags($source[0]['value']);
    $text = preg_replace("/\r|\n|\t/", "", $text);
    $text = Unicode::truncate($text, 320, TRUE, TRUE);
    if (!empty($text)) {
      $node->$target_field->setValue($text);
    }
  }
}

/**
 * Updates Curated List nodes that have manual lists.
 *
 * Multi-value document items become multiple manual lists instead.
 */
function mass_utility_post_update_curated_list_document_items(&$sandbox = NULL) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['current_index'] = 0;
    $sandbox['max'] = \Drupal::entityQuery('node')
      ->condition('type', 'curated_list')
      ->count()
      ->execute();
  }

  $batch_size = 50;
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'curated_list')
    ->condition('nid', $sandbox['current_index'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $result = Node::loadMultiple($nids);
  foreach ($result as $node) {
    $sandbox['current_index'] = $node->id();
    $list_items = $node->get('field_curatedlist_list_section')->referencedEntities();
    foreach ($list_items as $list_index => $list_item) {
      $list_item_type = $list_item->get('type')->first()->getValue()['target_id'];
      // If manual list section.
      if ($list_item_type == 'list_static') {
        $link_references = $list_item->get('field_liststatic_items')->referencedEntities();
        $field_liststatic_items = $list_item->get('field_liststatic_items')->getValue();
        $new_field_liststatic_items = [];
        foreach ($link_references as $link_index => $link_reference) {
          $link_reference_type = $link_reference->get('type')->first()->getValue()['target_id'];
          if ($link_reference_type == 'list_item_document' && $link_reference->get('field_liststaticdoc_item')->count() > 1) {
            $links = $link_reference->get('field_liststaticdoc_item')->getValue();
            // Looping through multi-value list item here.
            // For each link, create a new list_item_link paragraph, then add the reference to list_item.
            foreach ($links as $link) {
              $new_item_link = Paragraph::create([
                'type' => 'list_item_document',
              ]);
              $new_item_link->set('field_liststaticdoc_item', $link);
              $new_item_link->save();
              // Set the new item link id to the node.
              $new_field_liststatic_items[] = [
                'target_id' => $new_item_link->id(),
                'target_revision_id' => $new_item_link->getRevisionId(),
              ];
            }
          }
          else {
            // Manual list sections with only one entry or less get preserved.
            $new_field_liststatic_items[] = [
              'target_id' => $link_reference->id(),
              'target_revision_id' => $link_reference->getRevisionId(),
            ];
          }
        }
        // If the new list static array has changed, save.
        if (!empty($new_field_liststatic_items) && $new_field_liststatic_items != $field_liststatic_items) {
          $list_item->set('field_liststatic_items', $new_field_liststatic_items);
          $list_item->save();
        }
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
}

/**
 * Update all existing users with enabling the contact module in their accounts.
 */
function mass_utility_post_update_enable_contact_forms_all_users() {
  // Install contact module before enabling user accounts.
  $module_handler = \Drupal::service('module_handler');
  $module_installer = \Drupal::service('module_installer');

  if (!$module_handler->moduleExists('contact')) {
    $module_installer->install(['contact']);
  }

  // Enable all user accounts with the contact settings.
  /** @var \Drupal\user\UserInterface */
  $uids = \Drupal::entityQuery('user')
    ->condition('uid', 1, '>')
    ->execute();

  /** @var \Drupal\user\UserDataInterface $userData */
  $userData = \Drupal::service('user.data');
  foreach ($uids as $uid) {
    $userData->set('contact', $uid, 'enabled', TRUE);
  }
}

/**
 * DP-7726: sets a redirect from all-content to content.
 */
function mass_utility_post_update_set_all_content_view_redirect(&$sandbox) {
  Redirect::create([
    'redirect_source' => 'admin/ma-dash/all-content',
    'redirect_redirect' => 'internal:/admin/content',
    'language' => 'en',
    'status_code' => '301',
  ])->save();
}

/**
 * DP-7740: Populate new Subtype field with default value.
 */
function mass_utility_post_update_subtype_field() {
  $eq = \Drupal::entityQuery('node');
  $nids = $eq->condition('type', 'org_page')->execute();
  /** @var Drupal\node\Entity\Node[] $nodes */
  $nodes = Node::loadMultiple($nids);
  foreach ($nodes as $node) {
    $node->field_subtype->setValue('General Organization');
    $node->save();
  }
}

/**
 * Add binder vocabulary terms.
 */
function mass_utility_post_update_add_binder_terms() {
  // Machine name of the Taxonomy vocabulary.
  $vocab = 'binder_type';

  // Term names to be added.
  $terms = [
    'Law Library',
    'Report',
    'Audit',
    'Handbook',
    'Archive',
  ];
  foreach ($terms as $term) {
    Term::create([
      'parent' => [],
      'name' => $term,
      'vid' => $vocab,
    ])->save();
  }
}

/**
 * Queue nodes for setting org field to author's org.
 */
function mass_utility_post_update_set_node_org_fields(&$sandbox) {

  drush_print("Queueing nodes for org field update...");

  $nids = \Drupal::entityQuery('node')
    ->sort('nid')
    ->execute();

  foreach ($nids as $nid) {
    /** @var QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    /** @var QueueInterface $queue */
    $queue = $queue_factory->get('mass_entityreference_org_field');
    $item = new \stdClass();
    $item->nid = $nid;
    $queue->createItem($item);
  }

  drush_print('Completed queueing ' . count($nids) . ' nodes');
}

/**
 * DP-4027: Sets mass_utility modules weight higher than workbench_moderation.
 *
 * Workbench moderation module alters for /node/add form buttons. We want
 * to remove one of the buttons it adds via our utility module, so the
 * utility module's weight must be set to higher than 0.
 */
function mass_utility_post_update_higher_utility_module_weight(&$sandbox) {
  module_set_weight('mass_utility', 1);
}

/**
 * DP-4027: Move nodes from state 'archived' to 'unpublished'.
 */
function mass_utility_post_update_archived_to_unpublished(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  // NOTE: We have only ~600 archived nodes of total 50,000.
  // So we can safely move them in a single go.
  $query = \Drupal::entityQuery('node')
    ->condition('moderation_state', 'archived');
  $result = $query->execute();

  foreach ($result as $key => $nid) {
    $entity = \Drupal::service('entity_type.manager')->getStorage('node')->load($nid);
    $entity->get('moderation_state')->target_id = 'unpublished';
    $entity->save();
  }
}

/**
 * Empty post update hook to trigger cache rebuild.
 *
 * Runs after /admin/content/document-files view is imported.
 */
function mass_utility_post_update_docs_management_view() {
  // Empty.
}

/**
 * DP-7782 Queue link titles for checking and updating.
 */
function mass_utility_post_update_link_titles() {

  $database = \Drupal::database();
  $result = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('link');
  foreach ($result as $type => $fields) {
    if ($type == 'node') {
      foreach ($fields as $name => $info) {
        foreach ($info['bundles'] as $bundle) {

          $field_uri = $name . "_uri";
          $field_title = $name . "_title";

          // Find all entityreference links not already having blank titles.
          $result = $database->select($type . "__" . $name, 't')
            ->fields('t', [$field_uri, $field_title, 'entity_id'])
            ->condition($field_uri, "entity%", 'LIKE')
            ->condition($field_title, '', 'NOT LIKE')
            ->execute()
            ->fetchAll();

          foreach ($result as $row) {
            /** @var QueueFactory $queue_factory */
            $queue_factory = \Drupal::service('queue');
            /** @var QueueInterface $queue */
            $queue = $queue_factory->get('mass_entityreference_link_titles');
            $item = new \stdClass();
            $item->uri_field = $field_uri;
            $item->uri_value = $row->$field_uri;
            $item->title_field = $field_title;
            $item->title_value = $row->$field_title;
            $item->table = $type . "__" . $name;
            $item->entity_id = $row->entity_id;
            $queue->createItem($item);
          }
        }
      }
    }
  }
}

/**
 * Generate url aliases for all 'person' node entities.
 */
function mass_utility_post_update_person_url_alias() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('type', 'person');
  $result = $query->execute();
  $entity_storage = \Drupal::entityTypeManager()->getStorage('node');
  $person_nodes = $entity_storage->loadMultiple($result);
  // Update URL aliases.
  foreach ($person_nodes as $person_node) {
    \Drupal::service('pathauto.generator')->updateEntityAlias($person_node, 'bulkupdate', ['force' => TRUE]);
  }
}

/**
 * DP-8800: Fix media entity timestamps caused by incorrect migration.
 */
function mass_utility_post_update_fix_incorrect_media_timestamps(&$sandbox) {
  $connection = \Drupal::database();

  $num_updated_1 = $connection->update('media_field_revision')
    ->fields([
      'created' => 1511357094,
      'changed' => 1511357094,
    ])
    ->condition('created', \Drupal::time()->getRequestTime() + 3600, '>')
    ->execute();
  \Drupal::logger('mass_utility')
    ->notice("Fixed timestamps of $num_updated_1 media_field_revision records. See DP-8800 for details.");

  $num_updated_2 = $connection->update('media_field_data')
    ->fields([
      'created' => 1511357094,
      'changed' => 1511357094,
    ])
    ->condition('created', \Drupal::time()->getRequestTime() + 3600, '>')
    ->execute();
  \Drupal::logger('mass_utility')
    ->notice("Fixed timestamps of $num_updated_2 media_field_revision records. See DP-8800 for details.");
}

/**
 * DP-9380: Resolve more links showing on event pages when there are no events.
 */
function mass_utility_post_update_clear_views_cache_for_events() {
  // Empty update hook to trigger cache rebuild after events view
  // was deleted.
}

/**
 * DP-9234: Clear router cache after route_iframes config has changed.
 */
function mass_utility_post_update_clear_route_cache_after_route_iframe() {
  // Empty update hook to trigger cache rebuild after route iframes
  // config is updated.
}

/**
 * DP-9331: Transfers site map settings over to simple sitemap.
 */
function mass_utility_post_update_transfer_xmlsitemap_to_simple_sitemap(&$sandbox) {
  // First - enable simple sitemap if needed.
  $module_handler = \Drupal::service('module_handler');
  $module_installer = \Drupal::service('module_installer');
  if (!$module_handler->moduleExists('simple_sitemap')) {
    $module_installer->install(['simple_sitemap']);
  }

  /** @var Drupal\simple_sitemap\Simplesitemap $ssm */
  $ssm = \Drupal::service('simple_sitemap.generator');
  $changefreq_map = xmlsitemap_get_changefreq_options();

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['current_index'] = 0;
    $db = \Drupal::database();
    $query = $db->select('xmlsitemap');
    $or = $query->orConditionGroup();
    $or->condition('status_override', 0, '<>')
      ->condition('priority_override', 0, '<>');
    $sandbox['rows'] = $query
      ->fields('xmlsitemap')
      ->condition('type', 'node')
      ->condition('subtype', 'person', '<>')
      ->condition($or)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $sandbox['max'] = count($sandbox['rows']);
  }

  $rows = array_slice($sandbox['rows'], $sandbox['progress'], 1500);

  foreach ($rows as $xml) {
    $sandbox['current_index'] = $xml['id'];
    try {
      $ssm->setEntityInstanceSettings('node', $xml['id'], [
        'index' => $xml['status'],
        'priority' => $xml['priority'],
        'changefreq' => $changefreq_map[$xml['changefreq']],
      ]);
    }
    catch (Exception $e) {
      \Drupal::logger('mass_utility')->error($e->getMessage());
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);

}

/**
 * DP-9331: Transfers person nodes from xmlsitemap to simple sitemap.
 */
function mass_utility_post_update_handle_person_simple_sitemap(&$sandbox) {
  // First - enable simple sitemap if needed.
  $module_handler = \Drupal::service('module_handler');
  $module_installer = \Drupal::service('module_installer');
  if (!$module_handler->moduleExists('simple_sitemap')) {
    $module_installer->install(['simple_sitemap']);
  }

  /** @var Drupal\simple_sitemap\Simplesitemap $ssm */
  $ssm = \Drupal::service('simple_sitemap.generator');
  $storage_manager = \Drupal::entityTypeManager()->getStorage('node');

  $query = $storage_manager->getQuery();
  $query->condition('type', 'person');

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['current_index'] = 0;
    $count_query = clone $query;
    $sandbox['max'] = $count_query->count()->execute();
  }

  $query->range($sandbox['progress'], 100);
  $rows = $storage_manager->loadMultiple($query->execute());

  foreach ($rows as $node) {
    $sandbox['current_index'] = $node->id();
    try {
      $bio_page = $node->get('field_publish_bio_page')->getValue();
      $index = isset($bio_page[0]) ? $bio_page[0]['value'] : '0';
      // The remaining settings will be set to default bundle values.
      $ssm->setEntityInstanceSettings('node', $node->id(), [
        'index' => $index,
      ]);
    }
    catch (Exception $e) {
      \Drupal::logger('mass_utility')->error($e->getMessage());
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
}

/**
 * DP-8655: Perform initial pass to remove duplicate, bad file_managed records.
 */
function mass_utility_post_update_clean_file_managed() {
  mass_utility_clean_file_managed();
}

/**
 * Queue Person nodes for syncing org fields.
 */
function mass_utility_post_update_queue_person_org_sync() {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'person')
    ->execute();

  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $org_sync = $queue_factory->get('mass_utility_organization_sync');

  // Queue up org syncs in batches of 150.
  foreach (array_chunk($nids, 150) as $chunk) {
    $org_sync->createItem((object) ['ids' => $chunk]);
  }
}

/**
 * Queue Document Media entities for transferring org fields.
 */
function mass_utility_post_update_queue_document_sync() {
  $storage_manager = \Drupal::entityTypeManager()->getStorage('media');
  $query = $storage_manager->getQuery();
  $query->condition('bundle', 'document')
    ->exists('field_contributing_organization')
    ->exists('field_upload_file')
    ->condition('status', 1);
  $mids = $query->execute();

  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $org_transfer = $queue_factory->get('mass_utility_doc_organization_transfer');

  foreach (array_chunk($mids, 1000) as $chunk) {
    $org_transfer->createItem((object) ['ids' => $chunk]);
  }
}

/**
 * Queue unpublished Document Media entities for saving to ensure file is private.
 */
function mass_utility_post_update_unpublished_document_files(&$sandbox) {
  $storage_manager = \Drupal::entityTypeManager()->getStorage('media');
  $query = $storage_manager->getQuery();
  $query->condition('bundle', 'document')
    ->condition('status', 0);
  $mids = $query->execute();

  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $document_save = $queue_factory->get('mass_utility_document_save');

  foreach (array_chunk($mids, 150) as $chunk) {
    $document_save->createItem((object) ['ids' => $chunk]);
  }
}

/**
 * Updating nodes for Intended Audience field.
 */
function mass_utility_post_update_intended_audience(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  // List out the nodes that will need to be updated with their correct value.
  $both_equally = [
    3666, 3821, 95871, 92246, 183866, 168476, 14506, 15066, 339466, 6136, 81481, 317626, 93616, 34301, 5436, 91771, 17086, 106391, 13031, 102011, 51611, 5201, 17361, 46521, 36676, 41676, 154701, 83466, 67241, 17351, 64061, 32141, 9261, 296636, 136201, 199736, 19711, 172121, 18446, 237736, 92291, 205341, 30396, 62291, 32161, 25736, 7996, 193746, 21631, 92251, 213021, 183716, 223476, 23356, 44231, 205126, 184451, 116571, 188371, 247121, 49491, 168581, 160731, 218181, 33306, 70816, 61601, 204616, 127581, 67286, 152191, 51756, 22446, 11561, 51896, 70291, 6726, 5191, 6661, 8121, 18001, 7361, 112711, 194131, 14686, 14296, 23316, 17571, 146161, 92511, 36991, 7511, 98066, 11946, 23391, 22431, 10196, 8001, 14741, 20651, 14401, 17331, 36746, 17631, 332391, 112136, 72296, 11156, 10126, 91031, 86381, 191506, 280306, 70946, 22551,
  ];

  $professional = [
    55611, 311821, 265061, 14516, 322231, 107731, 315851, 215666, 51106, 19511, 13701, 139801, 190376, 25481, 228416, 15131, 16571, 81171, 5296, 112651, 51676, 312256, 78316, 64326, 124151, 259071, 119276, 250146, 70526, 28756, 55016, 139846, 46996, 90031, 6501, 345281, 22326, 333331, 161041, 100586, 98661, 329166, 223261, 166986, 87236, 257631, 97491, 17211, 161091, 61411, 23596, 39726, 12186, 97301, 52246, 239401, 25676, 19181, 92176, 56881, 138846, 51601, 186151, 335836, 13241, 25531, 355116, 151956, 217476, 78916, 51736, 49576, 82311, 265266, 97166, 28516, 252576, 18281, 330076, 253916, 142621, 37046, 335656, 85176, 6146, 214906, 216711, 24611, 62816, 309226, 31506, 330881, 313046, 47216, 5826, 199201, 61571, 22396, 39961, 251671, 18691, 224796, 76886, 86961, 50526, 7436, 91686, 309291, 25626, 61821, 70676, 239391, 55091, 15906, 7331, 13661, 28111, 5421, 195956, 204716, 98071, 76491, 133916, 90936, 242736, 67976, 181361, 10361, 309106, 164501, 174926, 335471, 230656, 6511, 58646, 63011, 147481, 335876, 299736, 313576, 8066, 98616, 99506, 61846, 106261, 272826, 164811, 106681, 138201, 24621, 76351, 70571, 316621, 186536, 150991, 25506, 15871, 24781, 167436, 207256, 112901, 98136, 17191, 73296, 301461, 223431, 94991, 151796, 24181, 117756, 79701, 51261, 101916, 74986, 262041, 74471, 17976, 91026, 49531, 126766, 65051, 56451, 161161, 305716, 125021, 170781, 329401, 21976, 333656, 119651, 13281, 14831, 20271, 119536, 60036, 191566, 101706, 272586, 78886, 12006, 260386, 50361, 100501, 342526, 249606, 50776, 118486, 60181, 152331, 335796, 239196, 151966, 32121, 5871, 14146, 5936, 84061, 7221, 98261, 9351, 90891, 79471, 59746, 92256, 48551, 7336, 56816, 16466, 18301, 17286, 76151, 15686, 315196, 319611, 17781, 197596, 78411, 76116, 140031, 163701, 45571, 24796, 207631, 222856, 6731, 30871, 92461, 78671, 225786, 66446, 58246, 167416, 168721, 28696, 160971, 21246, 205181, 31866, 118006, 61606, 338671, 18046, 62621, 85721, 268231, 318866, 77106, 44076, 251486, 76261, 186561, 142831, 225521, 75736, 214346, 42771, 318466, 147361, 103031, 131151, 55426, 34161, 66931, 30941, 238671, 31356, 72351, 16391, 24701, 8131, 7541, 13516, 59711, 19891, 113021, 33656, 214656, 170226, 5496, 91206, 104486, 104196, 21706, 14556, 21616, 80056, 257926, 13866, 81431, 83056, 223471, 45431, 95206, 215501, 200326, 325046, 318896, 81836, 99906, 79866, 55701, 113271, 87966, 216946, 152221, 155856, 81246, 5431, 200721, 23796, 18346, 57061, 329186, 5366, 111886, 188476, 98036, 161121, 78451, 16211, 6701, 35396, 105211, 319946, 16371, 319596, 366646, 317271, 37136, 184791, 312051, 272316, 21691, 24881, 28861, 117931, 207861, 308061, 184351, 215566, 109586, 315501, 147116, 110786, 50591, 345131, 17091, 78821, 105186, 298896, 65001, 17276, 114351, 241376, 74041, 86156, 28741, 343521, 43181, 247461, 24436, 79771, 74996, 137586, 324946, 170286, 10046, 44911, 320851, 8221, 105956, 99176, 31261, 323391, 20301, 10966, 319546, 299721, 104981, 77016, 315756, 169861, 150496, 18361, 319581, 24176, 195691, 46156, 119996, 13621, 319956, 23151, 103741, 199771, 75511, 101966, 51716, 217046, 30186, 49886, 116741, 161191, 58111, 14481, 257606, 323576, 219906, 108061, 6591, 259326, 5466, 85936, 46146, 74466, 11861, 5316, 335666, 323261, 184716, 330171, 318681, 191266, 275811, 286126, 95921, 63731, 224336, 214946, 164031, 223861, 167461, 77181, 50856, 36006, 74121, 28946, 254956, 7291, 88216, 272681, 94241, 74271, 62686, 16291, 14496, 59976, 48591, 194636, 164351, 127096, 65351, 237896, 89731, 94146, 67086, 18481, 169021, 63276, 65406, 59211, 121301, 194396, 30721, 199566, 318881, 229476, 260736, 323541, 24366, 47311, 76631, 235991, 193581, 73701, 84306, 197786, 71236, 98516, 7066, 216521, 17651, 98491, 7491, 80766, 5526, 107701, 313516, 81971, 100966, 225856, 21146, 319511, 87781, 15116, 48926, 117956, 76311, 41971, 334931, 85151, 239471, 114371, 14211, 201761, 164676, 335811, 234101, 19206, 59931, 315866, 215676, 29476, 80946, 215831, 30331, 49761, 134396, 280811, 310796, 42881, 111876, 337836, 66121, 86311, 319831, 319516, 25741, 50256, 181841, 61621, 263216, 109921, 204916, 100626, 91156, 99021, 86371, 59991, 335776, 102156, 319961, 316636, 88271, 101411, 335766, 113186, 117081, 22011, 72466, 94306, 111936, 75656, 171566, 253471, 323281, 285161, 218206, 189276, 319986, 179101, 184586, 50346, 216591, 339296, 65031, 130256, 77026, 79521, 59266, 211446, 25561, 227356, 141791, 88646, 154866, 236021, 64961, 45131, 317081, 45916, 91216, 99131, 67956, 35761, 234686, 318426, 144001, 237421, 228606, 205921, 96731, 207406, 31296, 99156, 278751, 67766, 67651, 138936, 52376, 43201, 75136, 145051, 261316, 323366, 121476, 203846, 99416, 72686, 85047, 214321, 184786, 102291, 28781, 214816, 59376, 38766, 83076, 277796, 52216, 43876, 52631, 65846, 215066, 6131, 47406, 184291, 62846, 187951, 346746, 65511, 52821, 155131, 45641, 85691, 270191, 92241, 14186, 21956, 216446, 323151, 135741, 42301, 119366, 94321, 367091, 62121, 15666, 87476, 15316, 225866, 140401, 194666, 216091, 313656, 41371, 78106, 84926, 255061, 323601, 45636, 183706, 331066, 252401, 136246, 115896, 81281, 99161, 64216, 86221, 51571, 32941, 66071, 102186, 122291, 22566, 15681, 26921, 82656, 150331, 103556, 14246, 59996, 353691, 146006, 95506, 191416, 255296, 84261, 48766, 204171, 134501, 319541, 152641, 363096, 204941, 170306, 86461, 201821, 91231, 33371, 97091, 78516, 96781, 299726, 323351, 279066, 81266, 249201, 35856, 6676, 64431, 88641, 328851, 81656, 346366, 107206, 362216, 266116, 22801, 220926, 328881, 313626, 260011, 59926, 187521, 318751, 85431, 192876, 112431, 188996, 119551, 60006, 312746, 346331, 55846, 240601, 43036, 161816, 11986, 90641, 39786, 46186, 161106, 113436, 147366, 13846, 114661, 34711, 28046, 17061, 205001, 98196, 223651, 57696, 99036, 104561, 264456, 75891, 135661, 329536, 127461, 9816, 60096, 276391, 325301, 57711, 301066, 61336, 248151, 48771, 86306, 250996, 104041, 191526, 319576, 251301, 8296, 132361, 89211, 32916, 31056, 71851, 52106, 13431, 70056, 61866, 91251, 359351, 206156, 24376, 324916, 69621, 286156, 179736, 139751, 72721, 24316, 31746, 280061, 131211, 99941, 284156, 70766, 253276, 205261, 277391, 277626, 70966, 84431, 27361, 314271, 17686, 115841, 62751, 43536, 345271, 222701, 7406, 21501, 329146, 11291, 79931, 72701, 54986, 12546, 57351, 183476, 77091, 223606, 329156, 317206, 112536, 344501, 17386, 221491, 214421, 206401, 242036, 62986, 52706, 24526, 97106, 59441, 226051, 143411, 292316, 240686, 5396, 333081, 323626, 87766, 21046, 91591, 100821, 105821, 313441, 323501, 49251, 82661, 59456, 86441, 5341, 185081, 64361, 7426, 58026, 160776, 5391, 19731, 93026, 17676, 243566, 60966, 16751, 44421, 332716, 335881, 7976, 292166, 47501, 65636, 14241, 6681, 16956, 8076, 16596, 195371, 13871, 333166, 77541, 64676, 83221, 156786, 7431, 99381, 5221, 7191, 48896, 81086, 7661, 9876, 107726, 217801, 21166, 161491, 17656, 17426, 32726, 31561, 13826, 11226, 333121, 42636, 17566, 6686, 7771, 51646, 104581, 75266, 196556, 11856, 91121, 51071, 60786, 19736, 30441, 80771, 13921, 79181, 6586, 49811, 59321, 212616, 102311, 5301, 23991, 59306, 333071, 330411, 78326, 46766, 16491, 92311, 87306, 13651, 56876, 25311, 5806, 33181, 18341, 12741, 196531, 32661, 206096, 51786, 16431, 23301, 11971, 18471, 61356, 150586, 17601, 5856, 161416, 319296, 7366, 76371, 44321, 5841, 274551, 250261, 18006, 91171, 328991, 17826, 86736, 312531, 33286, 25436, 13761, 74581, 20191, 70181, 45861, 7001, 318906, 90321, 75631, 15181, 99496, 342796, 70456, 14636, 16506, 152571, 5286, 42541, 53196, 83661, 24166, 319391, 189301, 34786, 319401, 85052, 341496, 18556, 92086, 333061, 17576, 17931, 17436, 16351, 319251, 42876, 16461, 17106, 141716, 329021, 91671, 56646, 238196, 32576, 320911, 27366, 107766, 24401, 280251, 11651, 204891, 31306, 14991, 284881, 104621, 29956, 367146, 25681,
  ];

  $personal = [
    98066, 21706, 43536, 7431, 341496, 104621, 8261, 6551, 33936, 12886, 18506, 130011, 18411, 306936, 19146, 28011, 368066, 294946, 219256, 17996, 13076, 59586, 28951, 25471, 12176, 23666, 58861, 67151, 12116, 37896, 53276, 30166, 265566, 42256, 24986, 218936, 102916, 30676, 16286, 53356, 13441, 24791, 189691, 3566, 52081, 22546, 36716, 64861, 33706, 23891, 20691, 28431, 24711, 23916, 178786, 19326, 6536, 19116, 48836, 28731, 175896, 49056, 339721, 35741, 30116, 226151, 33156, 109606, 143631, 42196, 12981, 30511, 100011, 44701, 106256, 31286, 39011, 199526, 32656, 225791, 7171, 155516, 40006, 16586, 8901, 102626, 131266, 15651, 33951, 273791, 84286, 11646, 51061, 79076, 17936, 65911, 23116, 102131, 26671, 126211, 19861, 109856, 22441, 49841, 239291, 60741, 19271, 11411, 24616, 22261, 329041, 35381, 17306, 347296, 28221, 85751, 32651, 48831, 60301, 15431, 66251, 93631, 113341, 50201, 21781, 19021, 29931, 138131, 24941, 26491, 107416, 47411, 16606, 35566, 318081, 153106, 17496, 19006, 11661, 97506, 86831, 65751, 109401, 68941, 88131, 30111, 127456, 19656, 355081, 271981, 20436, 16791, 30051, 20566, 348571, 249621, 53121, 29816, 94316, 61111, 23966, 14671, 53711, 7931, 12996, 87381, 15511, 12536, 7286, 94236, 150741, 39476, 100646, 14166, 39706, 184756, 20541, 27296, 109676, 14251, 68016, 18196, 93766, 7281, 79106, 29936, 70396, 135156, 17016, 53596, 50731, 95676, 332431, 51816, 80151, 59461, 185541, 91611, 52176, 23961, 20161, 53376, 95461, 317386, 373416, 112856, 82066, 35386, 180446, 60171, 27821, 90671, 79306, 83596, 77121, 123566, 81956, 61236, 33356, 31271, 104361, 164476, 15071, 103761, 280406, 18966, 109621, 216321, 17881, 228281, 24736, 19136, 97221, 81591, 12681, 20056, 17731, 16881, 138106, 87161, 18291, 312191, 17641, 20226, 17051, 83321, 20506, 20531, 15551, 19786, 48851, 58266, 20786, 19236, 59641, 42776, 75981, 18061, 25961, 22376, 107976, 21216, 14261, 11586, 11981, 43541, 92566, 300486, 18176, 214861, 105451, 18576, 36536, 10876, 12211, 240861, 18396, 16761, 76771, 40111, 20596, 20101, 67666, 351256, 347096, 16401, 16151, 31721, 67906, 15186, 63891, 29291, 17536, 14821, 96451, 202016, 79031, 7226, 5376, 13811, 49781, 195001, 145901, 21186, 11431, 108466, 18581, 19131, 17316, 11931, 8071, 5441, 6531, 23296, 325936, 117991, 12201, 11886, 16301, 6616, 133621, 11761, 85096, 30936, 11691, 40416, 5771, 23331, 6081, 7256, 11601, 11776, 7501, 23716, 24836, 16741, 11551, 6516, 23746, 315186, 27776, 6751, 24816, 112416, 144086, 15021, 333431, 49986, 17146, 10476, 13551, 13426, 30766, 16826, 14236, 6761, 11501, 134421, 20236, 5916, 58631, 31146, 32061, 24666, 155896, 106071, 11936, 23881, 95636, 24771, 178876, 32736, 25036, 7046, 12661, 24101, 14351, 12896, 141301, 8146, 25016, 23986, 56726, 24746, 14781, 51376, 16056, 112476, 14691, 11746, 20621, 24801, 19876, 50066, 15001, 23976, 24786, 12626, 14426, 15491, 14091, 262091, 25006, 11941, 164341, 14641, 11836, 25001, 15281, 16206, 8876, 38496, 53456, 14871, 17231, 65966, 11906, 17726, 14931, 56761, 22536, 14996, 43046, 35356, 12126, 121521, 14961, 73971, 9601, 34811, 23771, 23886, 15311, 15546, 14441, 14276, 223461, 15026, 16256, 105901, 15446, 95856, 16036, 18121, 10576, 14856, 15471, 17926, 16696, 14161, 17971, 333016, 24766, 35506, 214411, 47221, 17756, 66226, 17506, 10551, 239851, 20576,
  ];

  $unclear = [
    3326, 3331, 13881, 5966, 57366, 18676, 14216, 90246, 6656, 42391, 99936, 99076, 4726, 37326, 26826, 17111, 24661, 54681, 187961, 89041, 54776, 184411, 7376,
  ];

  $unset = [];

  $all_nodes = [];

  // Assign each node id a value.
  foreach ($both_equally as $value) {
    $all_nodes[$value] = 'Both equally';
  }

  foreach ($professional as $value) {
    $all_nodes[$value] = 'Professional (For their jobs)';
  }

  foreach ($personal as $value) {
    $all_nodes[$value] = 'Personal';
  }

  foreach ($unclear as $value) {
    $all_nodes[$value] = 'Unclear';
  }

  // Get a count of all nid's combined.
  $node_count = count($all_nodes);

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['max'] = $node_count;
  }

  $nodes = array_slice($all_nodes, $sandbox['progress'], 100, TRUE);

  // Loop through nodes 100 at a time.
  foreach ($nodes as $nid => $value) {
    $node = Node::load($nid);
    if (!empty($node) && $node != NULL && $node->hasField('field_intended_audience')) {
      $changed = $node->changed->value;
      $node->set('field_intended_audience', $value);
      $node->save();
      // Save node a second time to reset the changed date.
      $node->setChangedTime($changed)->save();

    }
    else {
      // Add to a list of the nodes that didn't update.
      $error = $value . ' - ' . $nid;
      array_push($unset, $error);
    }
    $sandbox['progress']++;
  }

  // If box is empty, value is 1 if not divide progress by max.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);

  if (count($unset) > 0 && $sandbox['#finished'] == 1) {
    $message = implode(", ", $unset);
    return t('All field_intended_audience instances for this batch are now updated except nodes: @message', ['@message' => $message]);
  }
  else {
    return t('All field_intended_audience instances are now updated for this batch (@progress of @total)', ['@progress' => $sandbox['progress'], '@total' => $sandbox['max']]);

  }

}

/**
 * Updating node draft status for Intended Audience field nodes.
 */
function mass_utility_post_update_intended_audience_draft_status() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $all_nodes = [];
  $node_array = [];
  $update = 0;

  $both_equally = [
    223476, 23391,
  ];

  $professional = [
    31506, 28111, 94991, 100501, 147361, 24366, 134396, 102186, 104561, 57711,
  ];

  $personal = [
    13441, 339721, 11646, 42776, 85096, 10576,
  ];

  // Assign each node id a value.
  foreach ($both_equally as $value) {
    $all_nodes[$value] = 'Both equally';
  }

  foreach ($professional as $value) {
    $all_nodes[$value] = 'Professional (For their jobs)';
  }

  foreach ($personal as $value) {
    $all_nodes[$value] = 'Personal';
  }

  foreach ($all_nodes as $nid => $value) {

    // Grab all revision id's.
    $node = Node::load($nid);
    if (!empty($node) && $node != NULL && $node->hasField('field_intended_audience')) {

      $revision_ids = \Drupal::service('entity_type.manager')->getStorage('node')->revisionIds($node);

      // Grab last revision.
      $last_revision_id = end($revision_ids);

      // Check that intended audience field is filled out on this revision.
      $last_node = \Drupal::service('entity_type.manager')->getStorage('node')->loadRevision($last_revision_id);

      if ($last_node->hasField('field_intended_audience') && !$last_node->get('field_intended_audience')->isEmpty()) {

        // Compare final revision to one four behind to see if dates match.
        $revision_count = count($revision_ids);
        $new_revision_id = end($revision_ids);
        $original_revision_id = $revision_ids[$revision_count - 4];

        $new_revision = \Drupal::service('entity_type.manager')->getStorage('node')->loadRevision($new_revision_id);
        $original_revision = \Drupal::service('entity_type.manager')->getStorage('node')->loadRevision($original_revision_id);

        $new_revision_changed = $new_revision->changed->value;
        $original_revision_changed = $original_revision->changed->value;

        if ($new_revision_changed == $original_revision_changed) {

          // We need to set the intended_audience field on this revision.
          // if this was using content moderation we'd use this line.
          // $node->setSyncing(TRUE);
          // With workbench we use.
          $original_revision->set('field_intended_audience', $value);
          $original_revision->doNotForceNewRevision = TRUE;
          $original_revision->setNewRevision(FALSE);
          $original_revision->isDefaultRevision(TRUE);
          $original_revision->set('status', TRUE);
          $original_revision->save();

          // Move the default revision from the last one so we can remove it.
          $last_node->doNotForceNewRevision = TRUE;
          $last_node->setNewRevision(FALSE);
          $last_node->isDefaultRevision(FALSE);
          $last_node->set('status', FALSE);
          $last_node->save();

          $node->doNotForceNewRevision = TRUE;
          $node->save();

          // Grab hopefully the draft.
          $revision_ids = \Drupal::service('entity_type.manager')->getStorage('node')->revisionIds($node);
          $draft_revision_id = $revision_ids[$revision_count - 3];
          $draft_revision = \Drupal::service('entity_type.manager')->getStorage('node')->loadRevision($draft_revision_id);
          $draft_revision_state = $draft_revision->get('moderation_state')->getString();

          if ($draft_revision_state != 'published') {

            // We need to set the intended_audience field on the draft.
            $draft_revision->set('field_intended_audience', $value);
            $draft_revision->doNotForceNewRevision = TRUE;
            $draft_revision->setNewRevision(FALSE);
            $draft_revision->isDefaultRevision(FALSE);
            $original_revision->save();

            // Grab the third to last revision.
            end($revision_ids);
            prev($revision_ids);
            $new_revision_id = prev($revision_ids);

            // Save a node revision and set it as default.
            $node = \Drupal::service('entity_type.manager')->getStorage('node')->loadRevision($new_revision_id);
            // If this was using content moderation we'd use this line.
            // $node->setSyncing(TRUE);.
            // With workbench we use.
            $node->doNotForceNewRevision = TRUE;
            $node->setNewRevision(FALSE);
            $node->isDefaultRevision(TRUE);
            $node->set('status', TRUE);
            $node->save();

            // Delete the unneeded revisions.
            $delete_revision_id = end($revision_ids);
            end($revision_ids);
            $delete_revision_id_2 = prev($revision_ids);
            \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($delete_revision_id);
            \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($delete_revision_id_2);

            $node_array[$update] = $nid;
            $update++;

            $draft_revision->save();

          }

        }

      }

    }

  }

  $message = implode(", ", $node_array);
  return t('All revisions are fixed for these nids: @message', ['@message' => $message]);

}

/**
 * Add values for Billing organizations taxonomy.
 */
function mass_utility_post_update_billing_organizations_1(&$sandbox) {
  // Machine name of the Taxonomy vocabulary.
  $vocab = 'billing_organizations';

  // Terms to be added.
  $items = [
    'Admin For Developmental Disabilities' => ['ANF', 'ADD'],
    'Administrative Law Appeals' => ['ANF', 'ALA'],
    'Amherst Police Department' => ['OTH', 'XAPD'],
    'Appeals Court' => ['OTH', 'APC'],
    'Appellate Tax Board' => ['ANF', 'ATB'],
    'Attorney Generals Office' => ['OTH', 'AGO'],
    'Barnstable Sheriffs Department' => ['OTH', 'SDC'],
    'Berkshire Community College' => ['OTH', 'BCC'],
    'Berkshire District Attorney' => ['OTH', 'BER'],
    'Berkshire Sheriffs Department' => ['OTH', 'SDB'],
    'Board of Bar Examiners' => ['OTH', 'BBE'],
    'Board of Library Commissioners' => ['OTH', 'BLC'],
    'Bridgewater State College' => ['OTH', 'BSC'],
    'Bristol Community College' => ['OTH', 'BRC'],
    'Bristol District Attorney' => ['OTH', 'BRI'],
    'Bristol Sheriffs Department' => ['OTH', 'BSD'],
    'Bunker Hill Community College' => ['OTH', 'BHC'],
    'Bureau of State Buildings' => ['ANF', 'BSB'],
    'Campaign and Political Finance' => ['OTH', 'CPF'],
    'Cannabis Control Commission' => ['OTH', 'CNB'],
    'Cape & Islands District Attorney' => ['OTH', 'CPI'],
    'Cape Cod Community College' => ['OTH', 'CCC'],
    'City of Chicopee' => ['OTH', 'XCPE'],
    'City of Ludlow' => ['OTH', 'XLUD'],
    'Civil Service Commission' => ['ANF', 'CSC'],
    'Comm. For The Deaf & Hard of Hearing' => ['EHS', 'MCD'],
    'Commission Against Discrimination' => ['OTH', 'CAD'],
    'Commission on Judicial Conduct' => ['OTH', 'CJC'],
    'Commission on the Status of Women' => ['OTH', 'CSW'],
    'Committee for Public Counsel Service' => ['OTH', 'CPC'],
    'Comptrollers Division' => ['TSS', 'CTR'],
    'Criminal History Systems Board' => ['EPS', 'CHS'],
    'Criminal Justice Training Council' => ['EPS', 'CJT'],
    'Department of Conservation & Recreation' => ['ENV', 'DCR'],
    'Department of Correction' => ['EPS', 'DOC'],
    'Department of Economic Development' => ['EED', 'SEA'],
    'Department of Elder Affairs' => ['EHS', 'ELD'],
    'Department of Environmental Protection' => ['ENV', 'EQE'],
    'Department of Fire Services' => ['EPS', 'DFS'],
    'Department of Food and Agriculture' => ['ENV', 'AGR'],
    'Department of Higher Education' => ['EDU', 'RGT'],
    'Department of Labor' => ['ELW', 'DOL'],
    'Department of Mental Health' => ['EHS', 'DMH'],
    'Department of Mental Retardation' => ['EHS', 'DMR'],
    'Department of Police' => ['EPS', 'POL'],
    'Department of Public Health' => ['EHS', 'DPH'],
    'Department of Public Safety' => ['EED', 'DPS'],
    'Department of Revenue' => ['ANF', 'DOR'],
    'Department of Social Services' => ['EHS', 'DSS'],
    'Department of Transitional Assistance' => ['EHS', 'WEL'],
    'Department of Workforce Development' => ['ELW', 'EOL'],
    'Department of Youth Services' => ['EHS', 'DYS'],
    'Dept Housing & Community Development' => ['EED', 'OCD'],
    'Dept of Industrial Accidents' => ['ELW', 'DIA'],
    'Dept of Public Utilities' => ['ENV', 'DPU'],
    'Dept of Veterans Services' => ['EHS', 'VET'],
    'Disabled Persons Protection Commission' => ['OTH', 'DAC'],
    'Div of Operational Services' => ['ANF', 'OSD'],
    'Div. of Capital Asset Management' => ['ANF', 'DCP'],
    'Division of Banks' => ['EED', 'DOB'],
    'Division of Human Resources' => ['ANF', 'HRD'],
    'Division of Insurance' => ['EED', 'DOI'],
    'Division of Labor Relations' => ['ELW', 'DLR'],
    'Division of Registration' => ['EED', 'REG'],
    'Division of Standards' => ['EED', 'DOS'],
    'Dukes Sheriffs Department' => ['OTH', 'SDD'],
    'Early Education and Care' => ['EDU', 'EEC'],
    'Eastern District Attorney' => ['OTH', 'EAS'],
    'Elementary and Secondary Education' => ['EDU', 'DOE'],
    'Emergency Management Agency' => ['EPS', 'CDA'],
    'EOTSS' => ['TSS', 'MIS'],
    'Essex Sheriffs Department' => ['OTH', 'SDE'],
    'Exec. Office For Health & Human Services' => ['EHS', 'EHS'],
    'Executive Office For Admin & Finance' => ['ANF', 'ANF'],
    'Executive Office of Economic Development' => ['EED', 'EED'],
    'Executive Office of Education' => ['EDU', 'EDU'],
    'Executive Office of Environmental Affairs' => ['ENV', 'ENV'],
    'Executive Office of Labor and Workforce' => ['ELW', 'ELW'],
    'Executive Office of Public Safety' => ['EPS', 'EPS'],
    'Executive Office of Transportation' => ['DOT', 'DOT'],
    'Fisheries Wildlife & Env Law Enforcement' => ['ENV', 'FWE'],
    'Fitchburg State College' => ['OTH', 'FSC'],
    'Framingham State College' => ['OTH', 'FRC'],
    'Franklin Sheriffs Department' => ['OTH', 'SDF'],
    'Gardner Town Hall' => ['OTH', 'XGTH'],
    'George Feingold Library' => ['ANF', 'LIB'],
    'Greenfield Community College' => ['OTH', 'GCC'],
    'Group Insurance Commission' => ['ANF', 'GIC'],
    'Hampden Sheriffs Department' => ['OTH', 'SDH'],
    'Hampshire County of Governments' => ['OTH', 'XHCG'],
    'Hampshire Sheriffs Department' => ['OTH', 'HSD'],
    'Health Care Finance and Policy' => ['OTH', 'HCF'],
    'Health Care Security Trust' => ['OTH', 'HST'],
    'Health Insurance Connector Authority' => ['OTH', 'XCCA'],
    'Health Policy Commission' => ['OTH', 'HPC'],
    'Holyoke Community College' => ['OTH', 'HCC'],
    'House of Representatives' => ['OTH', 'HOU'],
    'Joint Legislature' => ['OTH', 'LEG'],
    'Mass Bay Community College' => ['OTH', 'MBC'],
    'Mass College of Art' => ['OTH', 'MCA'],
    'Mass College of Liberal Arts' => ['OTH', 'NAC'],
    'Mass Commission For The Blind' => ['EHS', 'MCB'],
    'Mass District Attorneys' => ['OTH', 'DAA'],
    'Mass Educational Financing Authority' => ['OTH', 'XMEF'],
    'Mass Rehabilitation Commission' => ['EHS', 'MRC'],
    'Mass Water Resource Authority' => ['OTH', 'XWRA'],
    'Mass. Cultural Council' => ['OTH', 'ART'],
    'Massachusetts Gaming Commission' => ['OTH', 'MGC'],
    'Massachusetts Maritime Academy' => ['OTH', 'MMA'],
    'Massachusetts Office On Disability (MODR)' => ['ANF', 'OHA'],
    'Massachusetts School Building Author' => ['OTH', 'SBA'],
    'Massachusetts Secretary of State' => ['OTH', 'SEC'],
    'Massachusetts State Lottery' => ['OTH', 'LOT'],
    'Massachusetts State Treasurer' => ['OTH', 'TRE'],
    'Massachusetts Transportation Authority' => ['OTH', 'XMTA'],
    'Massasoit Community College' => ['OTH', 'MAS'],
    'MBTA' => ['OTH', 'MBT'],
    'Mental Health Legal Advisors' => ['OTH', 'MHL'],
    'Middle District Attorney' => ['OTH', 'MID'],
    'Middlesex Community College' => ['OTH', 'MCC'],
    'Middlesex Sheriffs Department' => ['OTH', 'SDM'],
    'Military Division' => ['EPS', 'MIL'],
    'Mount Wachusett Community College' => ['OTH', 'MWC'],
    'Nantucket Sheriffs Department' => ['OTH', 'NSD'],
    'Norfolk District Attorney' => ['OTH', 'NFK'],
    'Norfolk Sheriffs Department' => ['OTH', 'SDN'],
    'North Shore Community College' => ['OTH', 'NSC'],
    'Northampton City Hall' => ['OTH', 'XNHC'],
    'Northern Essex Community College' => ['OTH', 'NEC'],
    'Northern Middlesex District Attorney' => ['OTH', 'NOR'],
    'Northwestern District Attorney' => ['OTH', 'NWD'],
    'Off of Consumer Aff/ Div of Energy Resources' => ['ENV', 'ENE'],
    'Off of Consumer Affairs & Bus Regulations' => ['EED', 'SCA'],
    'Office For Refugees and Immigrants' => ['EHS', 'ORI'],
    'Office of The Chief Medical Examiner' => ['EPS', 'CME'],
    'Office of the Child Advocate' => ['OTH', 'OCA'],
    'Office of the Comptroller' => ['OTH', 'OSC'],
    'Office of The Governor' => ['OTH', 'GOV'],
    'Office of the Inspector General' => ['OTH', 'IGO'],
    'Parole Board' => ['EPS', 'PAR'],
    'Pension Reserves Investment Management' => ['OTH', 'XPRM'],
    'Plymouth District Attorney' => ['OTH', 'PLY'],
    'Plymouth Sheriffs Department' => ['OTH', 'SDP'],
    'Public Employee Retirement Adm' => ['OTH', 'PER'],
    'Quinsigamond Community College' => ['OTH', 'QCC'],
    'Roxbury Community College' => ['OTH', 'RCC'],
    'Salem State College' => ['OTH', 'SSA'],
    'Senate' => ['OTH', 'SEN'],
    'Sex Offender Registry Board' => ['EPS', 'SOR'],
    'Sheriffs Departments Association' => ['OTH', 'SDA'],
    'Soldiers Home - Holyoke' => ['EHS', 'HLY'],
    'Soldiers Home- Chelsea' => ['EHS', 'CHE'],
    'Springfield Tech. Community College' => ['OTH', 'STC'],
    'State Auditor' => ['OTH', 'SAO'],
    'State Ethics Commission' => ['OTH', 'ETH'],
    'State of Connecticut' => ['OTH', 'XSCT'],
    'State of Connecticut - Metropolitan' => ['OTH', 'XMDC'],
    'State Reclamation Board' => ['ENV', 'SRB'],
    'Suffolk County District Attorney' => ['OTH', 'SUF'],
    'Suffolk Sheriffs Department' => ['OTH', 'SDS'],
    'Supreme Judicial Court' => ['OTH', 'SJC'],
    'Teachers Retirement Board' => ['OTH', 'TRB'],
    'Tech Services' => ['TSS', 'TSS'],
    'Telecommunications and Cable' => ['EED', 'TAC'],
    'Trial Court' => ['OTH', 'TRC'],
    'UMASS' => ['OTH', 'XUMS'],
    'University of Mass System' => ['OTH', 'UMS'],
    'Victim & Witness Assistance Board' => ['OTH', 'VWA'],
    'West Springfield City Hall' => ['OTH', 'XWSC'],
    'Western District Attorney' => ['OTH', 'WES'],
    'Worcester Sheriffs Department' => ['OTH', 'SDW'],
    'Worcester State College' => ['OTH', 'WOR'],
  ];

  $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

  foreach ($items as $key => $values) {
    $term = $termStorage->create([
      'parent' => [],
      'vid' => $vocab,
      'field_billing_customer_name' => $key,
      'field_billing_customer_group' => $values[0],
      'field_billing_customer_code' => $values[1],
    ]);
    $term->preSave($termStorage);
    $term->save();
  }
}

/**
 * Add values for Billing organizations field on Organization Content Type.
 */
function mass_utility_post_update_billing_organizations_2(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $all_nodes = [
    '323686' => 'Tech Services',
    '388616' => 'Tech Services',
    '76721' => 'Appeals Court',
    '5316' => 'Appellate Tax Board',
    '63731' => 'Berkshire District Attorney',
    '24176' => 'Division of Registration',
    '386666' => 'Department of Public Health',
    '25676' => 'Division of Registration',
    '25481' => 'Division of Registration',
    '25706' => 'Division of Registration',
    '25681' => 'Division of Registration',
    '25666' => 'Division of Registration',
    '25591' => 'Division of Registration',
    '25506' => 'Division of Registration',
    '25436' => 'Division of Registration',
    '25366' => 'Division of Registration',
    '25311' => 'Division of Registration',
    '24861' => 'Division of Registration',
    '24621' => 'Division of Registration',
    '24181' => 'Division of Registration',
    '24166' => 'Division of Registration',
    '24881' => 'Division of Registration',
    '25561' => 'Division of Registration',
    '24661' => 'Division of Registration',
    '89211' => 'Division of Registration',
    '88776' => 'Division of Registration',
    '88336' => 'Division of Registration',
    '86736' => 'Division of Registration',
    '85761' => 'Division of Registration',
    '91121' => 'Division of Registration',
    '91041' => 'Division of Registration',
    '90641' => 'Division of Registration',
    '85161' => 'Division of Registration',
    '29971' => 'Executive Office of Environmental Affairs',
    '360001' => 'Department of Public Health',
    '260306' => 'Department of Public Health',
    '158846' => 'Department of Public Health',
    '345561' => 'Department of Public Health',
    '345546' => 'Department of Public Health',
    '409601' => 'Department of Public Health',
    '310441' => 'Department of Public Health',
    '408791' => 'Department of Public Health',
    '330561' => 'Department of Public Health',
    '326321' => 'Department of Public Health',
    '326311' => 'Department of Public Health',
    '326306' => 'Department of Public Health',
    '326301' => 'Department of Public Health',
    '326286' => 'Department of Public Health',
    '326281' => 'Department of Public Health',
    '326276' => 'Department of Public Health',
    '326251' => 'Department of Public Health',
    '247436' => 'Department of Public Health',
    '52246' => 'Department of Public Health',
    '87476' => 'Department of Public Health',
    '86746' => 'Department of Public Health',
    '86666' => 'Department of Public Health',
    '86631' => 'Department of Public Health',
    '86456' => 'Department of Public Health',
    '86426' => 'Department of Public Health',
    '86401' => 'Department of Public Health',
    '86361' => 'Department of Public Health',
    '64676' => 'Department of Public Health',
    '47216' => 'Department of Public Health',
    '45861' => 'Department of Public Health',
    '50186' => 'Bureau of State Buildings',
    '5836' => 'Bureau of State Buildings',
    '435846' => 'Cannabis Control Commission',
    '102166' => 'Cape & Islands District Attorney',
    '6096' => 'Office of the Comptroller',
    '417451' => 'Department of Conservation & Recreation',
    '205351' => 'Department of Conservation & Recreation',
    '81891' => 'Department of Conservation & Recreation',
    '51951' => 'Department of Conservation & Recreation',
    '6081' => 'Department of Conservation & Recreation',
    '39921' => 'Department of Conservation & Recreation',
    '7001' => 'Criminal History Systems Board',
    '362931' => 'Early Education and Care',
    '5841' => 'Fisheries Wildlife & Env Law Enforcement',
    '16126' => 'Fisheries Wildlife & Env Law Enforcement',
    '360951' => 'Department of Higher Education',
    '22576' => 'Dept of Industrial Accidents',
    '5391' => 'Dept of Industrial Accidents',
    '5936' => 'Division of Labor Relations',
    '13761' => 'Executive Office of Labor and Workforce',
    '441326' => 'Department of Public Health',
    '413971' => 'Department of Public Health',
    '412921' => 'Department of Public Health',
    '412661' => 'Department of Public Health',
    '405306' => 'Department of Public Health',
    '391386' => 'Department of Public Health',
    '389491' => 'Department of Public Health',
    '364061' => 'Department of Public Health',
    '347461' => 'Department of Public Health',
    '299866' => 'Department of Public Health',
    '187571' => 'Department of Public Health',
    '75311' => 'Department of Public Health',
    '34851' => 'Department of Public Health',
    '34596' => 'Department of Public Health',
    '34351' => 'Department of Public Health',
    '34321' => 'Department of Public Health',
    '34301' => 'Department of Public Health',
    '34081' => 'Department of Public Health',
    '32301' => 'Department of Public Health',
    '29476' => 'Department of Public Health',
    '28376' => 'Department of Public Health',
    '27491' => 'Department of Public Health',
    '23301' => 'Department of Public Health',
    '17851' => 'Department of Public Health',
    '354746' => 'Department of Public Health',
    '329161' => 'Department of Public Health',
    '345531' => 'Department of Public Health',
    '293316' => 'Department of Public Health',
    '284141' => 'Department of Public Health',
    '283661' => 'Department of Public Health',
    '102626' => 'Department of Public Health',
    '344641' => 'Department of Public Health',
    '342871' => 'Department of Public Health',
    '336296' => 'Department of Public Health',
    '144466' => 'Department of Public Health',
    '37386' => 'Department of Public Health',
    '26006' => 'Department of Public Health',
    '409701' => 'Department of Public Health',
    '409611' => 'Dept of Public Utilities',
    '64866' => 'Dept of Public Utilities',
    '60521' => 'Dept of Public Utilities',
    '35666' => 'Dept of Public Utilities',
    '16816' => 'Dept of Public Utilities',
    '15091' => 'Dept of Public Utilities',
    '5856' => 'Dept of Public Utilities',
    '47751' => 'Dept of Public Utilities',
    '5381' => 'Telecommunications and Cable',
    '5441' => 'Department of Transitional Assistance',
    '74116' => 'Executive Office of Labor and Workforce',
    '5496' => 'Executive Office of Labor and Workforce',
    '5376' => 'Executive Office of Labor and Workforce',
    '6591' => 'Department of Youth Services',
    '5396' => 'Executive Office For Admin & Finance',
    '366881' => 'Tech Services',
    '263216' => 'Tech Services',
    '97871' => 'Tech Services',
    '60951' => 'Tech Services',
    '13891' => 'Tech Services',
    '13741' => 'Tech Services',
    '13726' => 'Tech Services',
    '13641' => 'Tech Services',
    '6811' => 'Tech Services',
    '6011' => 'Tech Services',
    '428986' => 'Department of Public Health',
    '5826' => 'Division of Banks',
    '6511' => 'Div. of Capital Asset Management',
    '5251' => 'Fisheries Wildlife & Env Law Enforcement',
    '5771' => 'Fisheries Wildlife & Env Law Enforcement',
    '13921' => 'Division of Insurance',
    '5221' => 'Department of Revenue',
    '5916' => 'Fisheries Wildlife & Env Law Enforcement',
    '15761' => 'Fisheries Wildlife & Env Law Enforcement',
    '15756' => 'Fisheries Wildlife & Env Law Enforcement',
    '15731' => 'Fisheries Wildlife & Env Law Enforcement',
    '99506' => 'Division of Registration',
    '93041' => 'Division of Registration',
    '31986' => 'Division of Registration',
    '27496' => 'Division of Registration',
    '6681' => 'Division of Registration',
    '99496' => 'Department of Public Health',
    '172121' => 'Department of Public Health',
    '13851' => 'Executive Office of Environmental Affairs',
    '326296' => 'Department of Public Health',
    '6551' => 'Eastern District Attorney',
    '14831' => 'Civil Service Commission',
    '13591' => 'Executive Office For Admin & Finance',
    '10606' => 'Administrative Law Appeals',
    '5866' => 'Executive Office For Admin & Finance',
    '27911' => 'Executive Office of Education',
    '23776' => 'Elementary and Secondary Education',
    '14516' => 'Early Education and Care',
    '14481' => 'Executive Office of Education',
    '437216' => 'Department of Elder Affairs',
    '425876' => 'Executive Office of Environmental Affairs',
    '136371' => 'Executive Office of Environmental Affairs',
    '125011' => 'Executive Office of Environmental Affairs',
    '78126' => 'Executive Office of Environmental Affairs',
    '42251' => 'Executive Office of Environmental Affairs',
    '41206' => 'Executive Office of Environmental Affairs',
    '39361' => 'Executive Office of Environmental Affairs',
    '18066' => 'Executive Office of Environmental Affairs',
    '12761' => 'Department of Conservation & Recreation',
    '7511' => 'Fisheries Wildlife & Env Law Enforcement',
    '5871' => 'Executive Office of Environmental Affairs',
    '438451' => 'Exec. Office For Health & Human Services',
    '437806' => 'Exec. Office For Health & Human Services',
    '424576' => 'Exec. Office For Health & Human Services',
    '418061' => 'Exec. Office For Health & Human Services',
    '415316' => 'Exec. Office For Health & Human Services',
    '156201' => 'Exec. Office For Health & Human Services',
    '134651' => 'Exec. Office For Health & Human Services',
    '133446' => 'Exec. Office For Health & Human Services',
    '119191' => 'Exec. Office For Health & Human Services',
    '113026' => 'Exec. Office For Health & Human Services',
    '107861' => 'Exec. Office For Health & Human Services',
    '97546' => 'Exec. Office For Health & Human Services',
    '34566' => 'Exec. Office For Health & Human Services',
    '21706' => 'Exec. Office For Health & Human Services',
    '13871' => 'Exec. Office For Health & Human Services',
    '13621' => 'Exec. Office For Health & Human Services',
    '7361' => 'Exec. Office For Health & Human Services',
    '6531' => 'Exec. Office For Health & Human Services',
    '6661' => 'Exec. Office For Health & Human Services',
    '5436' => 'Department of Elder Affairs',
    '41786' => 'Exec. Office For Health & Human Services',
    '132346' => 'Exec. Office For Health & Human Services',
    '259326' => 'Dept Housing & Community Development',
    '250131' => 'Dept Housing & Community Development',
    '227351' => 'Dept Housing & Community Development',
    '140176' => 'Dept Housing & Community Development',
    '136396' => 'Dept Housing & Community Development',
    '136326' => 'Dept Housing & Community Development',
    '58356' => 'Dept Housing & Community Development',
    '7491' => 'Dept Housing & Community Development',
    '414111' => 'Executive Office of Labor and Workforce',
    '104971' => 'Executive Office of Labor and Workforce',
    '32916' => 'Executive Office of Labor and Workforce',
    '14296' => 'Executive Office of Labor and Workforce',
    '48896' => 'Department of Fire Services',
    '14066' => 'Military Division',
    '5471' => 'Executive Office of Public Safety',
    '5301' => 'Executive Office of Public Safety',
    '44761' => 'Tech Services',
    '44426' => 'Tech Services',
    '44371' => 'Tech Services',
    '44176' => 'Tech Services',
    '31866' => 'Tech Services',
    '389236' => 'Trial Court',
    '389211' => 'Trial Court',
    '14556' => 'Office of The Governor',
    '7771' => 'Group Insurance Commission',
    '5901' => 'Department of Public Safety',
    '5191' => 'Dept Housing & Community Development',
    '87236' => 'Division of Human Resources',
    '11226' => 'Division of Human Resources',
    '77541' => 'Civil Service Commission',
    '326271' => 'Department of Public Health',
    '5981' => 'Executive Office of Environmental Affairs',
    '5466' => 'Board of Bar Examiners',
    '6501' => 'Commission Against Discrimination',
    '8221' => 'Mass Commission For The Blind',
    '13691' => 'Comm. For The Deaf & Hard of Hearing',
    '6641' => 'Commission on Judicial Conduct',
    '31666' => 'Commission on the Status of Women',
    '259816' => 'Supreme Judicial Court',
    '67286' => 'Trial Court',
    '66861' => 'Supreme Judicial Court',
    '63511' => 'Trial Court',
    '56451' => 'Trial Court',
    '28891' => 'Massachusetts State Treasurer',
    '13626' => 'Fisheries Wildlife & Env Law Enforcement',
    '51106' => 'Appeals Court',
    '51611' => 'Trial Court',
    '51646' => 'Trial Court',
    '51601' => 'Trial Court',
    '166051' => 'Trial Court',
    '248681' => 'Trial Court',
    '248836' => 'Trial Court',
    '51676' => 'Trial Court',
    '248461' => 'Trial Court',
    '51716' => 'Trial Court',
    '51736' => 'Trial Court',
    '338661' => 'Trial Court',
    '50856' => 'Trial Court',
    '249211' => 'Trial Court',
    '51071' => 'Supreme Judicial Court',
    '56556' => 'Trial Court',
    '51896' => 'Trial Court',
    '51756' => 'Trial Court',
    '51786' => 'Trial Court',
    '250606' => 'Trial Court',
    '248591' => 'Trial Court',
    '212646' => 'Trial Court',
    '47501' => 'Trial Court',
    '248196' => 'Trial Court',
    '417916' => 'Department of Food and Agriculture',
    '229886' => 'Department of Food and Agriculture',
    '195966' => 'Department of Food and Agriculture',
    '26491' => 'Department of Food and Agriculture',
    '13661' => 'Department of Food and Agriculture',
    '6761' => 'Department of Correction',
    '75351' => 'Off of Consumer Aff/ Div of Energy Resources',
    '74746' => 'Off of Consumer Aff/ Div of Energy Resources',
    '74466' => 'Off of Consumer Aff/ Div of Energy Resources',
    '71546' => 'Off of Consumer Aff/ Div of Energy Resources',
    '71416' => 'Off of Consumer Aff/ Div of Energy Resources',
    '48836' => 'Off of Consumer Aff/ Div of Energy Resources',
    '54401' => 'Department of Environmental Protection',
    '53276' => 'Department of Environmental Protection',
    '14241' => 'Department of Environmental Protection',
    '5806' => 'Department of Mental Health',
    '437131' => 'Department of Mental Health',
    '5341' => 'Department of Revenue',
    '59456' => 'Department of Revenue',
    '106416' => 'Executive Office of Transportation',
    '99186' => 'Executive Office of Transportation',
    '99141' => 'Executive Office of Transportation',
    '99051' => 'Executive Office of Transportation',
    '98961' => 'Executive Office of Transportation',
    '77261' => 'Executive Office of Transportation',
    '77226' => 'Executive Office of Transportation',
    '60961' => 'Executive Office of Transportation',
    '32161' => 'Executive Office of Transportation',
    '27881' => 'Executive Office of Transportation',
    '6536' => 'Executive Office of Transportation',
    '5456' => 'Admin For Developmental Disabilities',
    '55611' => 'Mass District Attorneys',
    '6136' => 'Emergency Management Agency',
    '26951' => 'Fisheries Wildlife & Env Law Enforcement',
    '26406' => 'Fisheries Wildlife & Env Law Enforcement',
    '26356' => 'Fisheries Wildlife & Env Law Enforcement',
    '22331' => 'Fisheries Wildlife & Env Law Enforcement',
    '22826' => 'Fisheries Wildlife & Env Law Enforcement',
    '14686' => 'Fisheries Wildlife & Env Law Enforcement',
    '6731' => 'Executive Office of Environmental Affairs',
    '203706' => 'Executive Office of Economic Development',
    '6756' => 'Health Policy Commission',
    '6676' => 'Health Policy Commission',
    '23356' => 'Victim & Witness Assistance Board',
    '259991' => 'Executive Office of Environmental Affairs',
    '5311' => 'Executive Office of Environmental Affairs',
    '5366' => 'Massachusetts Office On Disability (MODR)',
    '5201' => 'Parole Board',
    '13811' => 'Executive Office of Transportation',
    '433431' => 'Mass Rehabilitation Commission',
    '36991' => 'Mass Rehabilitation Commission',
    '12041' => 'Massachusetts State Lottery',
    '6726' => 'Department of Police',
    '6801' => 'Department of Police',
    '6586' => 'Tech Services',
    '15181' => 'Exec. Office For Health & Human Services',
    '39011' => 'Exec. Office For Health & Human Services',
    '31816' => 'Executive Office of Labor and Workforce',
    '31516' => 'Executive Office of Labor and Workforce',
    '8296' => 'Executive Office of Labor and Workforce',
    '6146' => 'Executive Office of Labor and Workforce',
    '14146' => 'Fisheries Wildlife & Env Law Enforcement',
    '5966' => 'Criminal Justice Training Council',
    '184756' => 'Norfolk District Attorney',
    '13706' => 'Office For Refugees and Immigrants',
    '415871' => 'Attorney Generals Office',
    '9876' => 'Attorney Generals Office',
    '5431' => 'Attorney Generals Office',
    '291891' => 'Department of Public Safety',
    '286756' => 'Department of Public Safety',
    '99271' => 'Department of Public Safety',
    '92541' => 'Division of Standards',
    '88166' => 'Division of Registration',
    '5286' => 'Off of Consumer Affairs & Bus Regulations',
    '286111' => 'Office of The Governor',
    '78726' => 'Office of The Governor',
    '54311' => 'Office of The Governor',
    '52646' => 'Office of The Governor',
    '36376' => 'Office of The Governor',
    '34701' => 'Office of The Governor',
    '34161' => 'Office of The Governor',
    '27186' => 'Office of The Governor',
    '19666' => 'Office of The Governor',
    '18281' => 'Office of The Governor',
    '15241' => 'Office of The Governor',
    '6516' => 'Office of The Governor',
    '6046' => 'Executive Office of Labor and Workforce',
    '296856' => 'Department of Public Safety',
    '99316' => 'Department of Public Safety',
    '99301' => 'Department of Public Safety',
    '99276' => 'Department of Public Safety',
    '314251' => 'Massachusetts State Treasurer',
    '42636' => 'Public Employee Retirement Adm',
    '36311' => 'Massachusetts State Treasurer',
    '6866' => 'Pension Reserves Investment Management',
    '6846' => 'Massachusetts State Treasurer',
    '6836' => 'Massachusetts School Building Author',
    '6816' => 'Massachusetts State Treasurer',
    '6751' => 'Massachusetts State Treasurer',
    '6686' => 'Massachusetts State Treasurer',
    '5781' => 'Massachusetts State Treasurer',
    '5516' => 'Massachusetts State Treasurer',
    '5526' => 'Massachusetts State Treasurer',
    '144916' => 'Executive Office of Environmental Affairs',
    '137301' => 'Executive Office of Environmental Affairs',
    '6626' => 'Executive Office of Environmental Affairs',
    '6131' => 'Executive Office of Public Safety',
    '6596' => 'Office of the Child Advocate',
    '5421' => 'Office of the Inspector General',
    '27511' => 'State Auditor',
    '23206' => 'State Auditor',
    '5296' => 'State Auditor',
    '51261' => 'Div of Operational Services',
    '311511' => 'Public Employee Retirement Adm',
    '299616' => 'Public Employee Retirement Adm',
    '299621' => 'Public Employee Retirement Adm',
    '299511' => 'Public Employee Retirement Adm',
    '298881' => 'Public Employee Retirement Adm',
    '298791' => 'Public Employee Retirement Adm',
    '298616' => 'Public Employee Retirement Adm',
    '298516' => 'Public Employee Retirement Adm',
    '298511' => 'Public Employee Retirement Adm',
    '298291' => 'Public Employee Retirement Adm',
    '298591' => 'Public Employee Retirement Adm',
    '298586' => 'Public Employee Retirement Adm',
    '298581' => 'Public Employee Retirement Adm',
    '298571' => 'Public Employee Retirement Adm',
    '298566' => 'Public Employee Retirement Adm',
    '298561' => 'Public Employee Retirement Adm',
    '298556' => 'Public Employee Retirement Adm',
    '298551' => 'Public Employee Retirement Adm',
    '298546' => 'Public Employee Retirement Adm',
    '298541' => 'Public Employee Retirement Adm',
    '298531' => 'Public Employee Retirement Adm',
    '298536' => 'Public Employee Retirement Adm',
    '298271' => 'Public Employee Retirement Adm',
    '298261' => 'Public Employee Retirement Adm',
    '298201' => 'Public Employee Retirement Adm',
    '298191' => 'Public Employee Retirement Adm',
    '298186' => 'Public Employee Retirement Adm',
    '298256' => 'Public Employee Retirement Adm',
    '298251' => 'Public Employee Retirement Adm',
    '298246' => 'Public Employee Retirement Adm',
    '298241' => 'Public Employee Retirement Adm',
    '298236' => 'Public Employee Retirement Adm',
    '298231' => 'Public Employee Retirement Adm',
    '298226' => 'Public Employee Retirement Adm',
    '298221' => 'Public Employee Retirement Adm',
    '298216' => 'Public Employee Retirement Adm',
    '298211' => 'Public Employee Retirement Adm',
    '298006' => 'Public Employee Retirement Adm',
    '298011' => 'Public Employee Retirement Adm',
    '298026' => 'Public Employee Retirement Adm',
    '298031' => 'Public Employee Retirement Adm',
    '298036' => 'Public Employee Retirement Adm',
    '298041' => 'Public Employee Retirement Adm',
    '297761' => 'Public Employee Retirement Adm',
    '297741' => 'Public Employee Retirement Adm',
    '297726' => 'Public Employee Retirement Adm',
    '297716' => 'Public Employee Retirement Adm',
    '297791' => 'Public Employee Retirement Adm',
    '297801' => 'Public Employee Retirement Adm',
    '297806' => 'Public Employee Retirement Adm',
    '297811' => 'Public Employee Retirement Adm',
    '297816' => 'Public Employee Retirement Adm',
    '297826' => 'Public Employee Retirement Adm',
    '297831' => 'Public Employee Retirement Adm',
    '297836' => 'Public Employee Retirement Adm',
    '297841' => 'Public Employee Retirement Adm',
    '297546' => 'Public Employee Retirement Adm',
    '297671' => 'Public Employee Retirement Adm',
    '297666' => 'Public Employee Retirement Adm',
    '297661' => 'Public Employee Retirement Adm',
    '297651' => 'Public Employee Retirement Adm',
    '297641' => 'Public Employee Retirement Adm',
    '297631' => 'Public Employee Retirement Adm',
    '297626' => 'Public Employee Retirement Adm',
    '297616' => 'Public Employee Retirement Adm',
    '297601' => 'Public Employee Retirement Adm',
    '297591' => 'Public Employee Retirement Adm',
    '297506' => 'Public Employee Retirement Adm',
    '297301' => 'Public Employee Retirement Adm',
    '297391' => 'Public Employee Retirement Adm',
    '297396' => 'Public Employee Retirement Adm',
    '297401' => 'Public Employee Retirement Adm',
    '297411' => 'Public Employee Retirement Adm',
    '297416' => 'Public Employee Retirement Adm',
    '297421' => 'Public Employee Retirement Adm',
    '297431' => 'Public Employee Retirement Adm',
    '297441' => 'Public Employee Retirement Adm',
    '297446' => 'Public Employee Retirement Adm',
    '297236' => 'Public Employee Retirement Adm',
    '297246' => 'Public Employee Retirement Adm',
    '297226' => 'Public Employee Retirement Adm',
    '297176' => 'Public Employee Retirement Adm',
    '297166' => 'Public Employee Retirement Adm',
    '297146' => 'Public Employee Retirement Adm',
    '297136' => 'Public Employee Retirement Adm',
    '297131' => 'Public Employee Retirement Adm',
    '288231' => 'Public Employee Retirement Adm',
    '288221' => 'Public Employee Retirement Adm',
    '287871' => 'Public Employee Retirement Adm',
    '287841' => 'Public Employee Retirement Adm',
    '287811' => 'Public Employee Retirement Adm',
    '287771' => 'Public Employee Retirement Adm',
    '281456' => 'Public Employee Retirement Adm',
    '281411' => 'Public Employee Retirement Adm',
    '281276' => 'Public Employee Retirement Adm',
    '281201' => 'Public Employee Retirement Adm',
    '281166' => 'Public Employee Retirement Adm',
    '280911' => 'Public Employee Retirement Adm',
    '280891' => 'Public Employee Retirement Adm',
    '280841' => 'Public Employee Retirement Adm',
    '280801' => 'Public Employee Retirement Adm',
    '279421' => 'Public Employee Retirement Adm',
    '279391' => 'Public Employee Retirement Adm',
    '279281' => 'Public Employee Retirement Adm',
    '102151' => 'Public Employee Retirement Adm',
    '13701' => 'Public Employee Retirement Adm',
    '383086' => 'Department of Public Health',
    '439826' => 'Executive Office of Transportation',
    '6616' => 'Sex Offender Registry Board',
    '6656' => 'Executive Office of Public Safety',
    '13651' => 'State Ethics Commission',
    '6701' => 'George Feingold Library',
    '69501' => 'Office of the Comptroller',
    '55031' => 'Department of Environmental Protection',
    '442246' => 'Exec. Office For Health & Human Services',
    '436401' => 'Cannabis Control Commission',
    '434246' => 'Office of The Governor',
    '432241' => 'Exec. Office For Health & Human Services',
    '421506' => 'Office of The Governor',
    '419631' => 'Office of The Governor',
    '389196' => 'Dept of Veterans Services',
    '388646' => 'Tech Services',
    '99421' => 'Department of Public Safety',
    '13881' => 'Admin For Developmental Disabilities',
    '13826' => 'Department of Public Safety',
    '6606' => 'Executive Office of Public Safety',
  ];

  // Get a count of all nid's combined.
  $node_count = count($all_nodes);
  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['max'] = $node_count;
  }
  $nodes = array_slice($all_nodes, $sandbox['progress'], 100, TRUE);
  $unset = [];

  // Loop through nodes 100 at a time.
  foreach ($nodes as $nid => $value) {
    $node = Node::load($nid);
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['field_billing_customer_name' => $value]);
    if ($terms != NULL) {
      $term = reset($terms);
    }

    if (!empty($node) && $node != NULL && $node->hasField('field_billing_organization') && !empty($term)) {
      // Set organization field.
      $node->field_billing_organization->entity = $term;
      $time = $node->getChangedTime();
      $node->save();

      // Needs to be saved again to reset timestamp to its original value.
      $node->setChangedTime($time);
      $node->save();
    }
    else {
      // Add to a list of the nodes that didn't update.
      $error = $value . ' - ' . $nid;
      array_push($unset, $error);
    }
    $sandbox['progress']++;
  }
  // If box is empty, value is 1 if not divide progress by max.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if (count($unset) > 0 && $sandbox['#finished'] == 1) {
    $message = implode(", ", $unset);
    return t('All field_billing_organization instances for this batch are now updated except nodes: @message', ['@message' => $message]);
  }
  else {
    return t('All field_billing_organization instances are now updated for this batch (@progress of @total)', ['@progress' => $sandbox['progress'], '@total' => $sandbox['max']]);
  }
}

/**
 * Add values for Service page template for service pages.
 */
function mass_utility_post_update_service_page_template(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $batch_size = 50;

  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  $query = $nodeStorage->getQuery();

  $query->condition('type', 'service_page');

  // Set up batch variables on first run.
  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $sandbox['progress'] = 0;
    $sandbox['current_index'] = 0;
    $count_query = clone $query;
    $sandbox['max'] = $count_query->count()->execute();
  }

  $query->condition('nid', $sandbox['current_index'], '>')
    ->sort('nid')
    ->range(0, $batch_size);

  $rows = $nodeStorage->loadMultiple($query->execute());

  foreach ($rows as $node) {
    $sandbox['current_index'] = $node->id();
    if (!$node->isLatestRevision()) {
      $latest_id = $nodeStorage->getLatestRevisionId($node->id());
      if (!empty($latest_id)) {
        $latest = $nodeStorage->loadRevision($latest_id);
      }
      $latest->field_template = 'default';
      $time = $latest->getChangedTime();
      $latest->save();
      $latest->setChangedTime($time);
      $latest->save();
    }
    else {
      $node->field_template = 'default';
      $time = $node->getChangedTime();
      $node->save();
      $node->setChangedTime($time);
      $node->save();
    }
    $sandbox['progress']++;
  }

  // If box is empty, value is 1 if not divide progress by max.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] == 1) {
    return t('The default value for the Service page template field for all service pages has been set.');
  }
}

/**
 * Queue the creation of content moderation states for document media.
 */
function mass_utility_post_update_document_moderation(array &$sandbox) {
  // Setup the query to be used for all media lookups in this batch process.
  $query = \Drupal::database()->select('media_field_revision', 'r');
  $query->join('media_field_data', 'd', 'd.mid = r.mid');
  $query->condition('d.bundle', 'document');

  // Perform a search for all document media entities and queue them to have a
  // content moderation state generated.
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['offset'] = 0;
    $sandbox['batch_size'] = 10000;
    $count_query = clone $query;
    $sandbox['max'] = ceil($count_query->countQuery()->execute()->fetchField() / $sandbox['batch_size']);
  }

  $query->addField('r', 'mid', 'content_entity_id');
  $query->addField('r', 'vid', 'content_entity_revision_id');
  $query->addField('r', 'status', 'moderation_state');
  $query->fields('r', [
    'langcode',
    'uid',
    'default_langcode',
    'revision_translation_affected',
  ]);
  $query->range($sandbox['offset'], $sandbox['batch_size'])
    ->orderBy('r.changed', 'DESC');
  $media_data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

  if (!empty($media_data)) {
    $queue = \Drupal::queue('mass_utility_populate_media_states');
    $batched = array_chunk($media_data, 50);
    foreach ($batched as $media_batch) {
      $queue->createItem($media_batch);
    }
  }

  $sandbox['offset'] += count($media_data);
  $sandbox['progress']++;
  if ($sandbox['progress'] <= $sandbox['max']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['max'];
  }
  else {
    $sandbox['#finished'] = 1;
  }

}

/**
 * Update unpublished documents to draft state.
 */
function mass_utility_post_update_document_moderation_drafts(&$sandbox) {

  // Process docs by groups of 1000 (arbitrary value).
  $limit = 1000;

  // Total docs that must be visited.
  $items = \Drupal::database();
  // Grab the documents that are unpublished.
  $items_query = $items->select('media_field_data', 'd');
  $items_query->condition('d.bundle', 'document');
  $items_query->condition('d.status', '0', '=');
  // Join together the data and revision tables.
  $items_query->join('media_field_revision', 'r', 'd.mid = r.mid');
  // Get only the current revision.
  $items_query->condition('r.status', '1', '=');
  // Join the moderation table using the current revisions.
  $items_query->join('content_moderation_state_field_data', 'c', 'r.vid = c.content_entity_revision_id');
  $items_query->condition('c.moderation_state', 'unpublished', '=');
  $items_query->fields('d', ['mid']);

  // Use the sandbox at your convenience to store the information needed
  // to track progression between successive calls to the function.
  if (!isset($sandbox['progress'])) {

    // Count the items in the query.
    $count_query = clone $items_query;
    $count = $count_query->countQuery()->execute()->fetchAll();
    if ($count !== NULL) {
      $sandbox['max'] = $count[0]->expression;
    }

    // The count of docs visited so far.
    $sandbox['progress'] = 0;

    // A place to store messages during the run.
    $sandbox['messages'] = [];

    // Last doc read via the query.
    $sandbox['current_doc'] = -1;
  }

  // Find all media documents in the unpublished moderation state.
  $items_query->where('d.mid > :mid', [':mid' => $sandbox['current_doc']]);
  $items_query->range(0, $limit);
  $items_query->orderBy('d.mid', 'ASC');
  $result = $items_query->execute();

  // Update all unpublished documents to draft state.
  foreach ($result as $doc) {
    $entity = Media::load($doc->mid);
    $entity->set('moderation_state', 'draft');
    $entity->save();

    // Update our progress information.
    $sandbox['progress']++;
    $sandbox['current_doc'] = $doc->mid;
  }

  // Set the "finished" status.
  $sandbox['#finished'] = $sandbox['progress'] >= $sandbox['max'] ? TRUE : $sandbox['progress'] / $sandbox['max'];

  // Set up a per-run message; Make a copy of $sandbox so we can change it.
  $sandbox_status = $sandbox;

  // Don't want them in the output.
  unset($sandbox_status['messages']);
  $sandbox['messages'][] = print_r($sandbox_status);
  if ($sandbox['#finished']) {

    // hook_update_N() may optionally return a string which will be displayed
    // to the user.
    $final_message = print_r($sandbox['messages']);
    return t('Document(s) updated: @message', [
      '@message' => $final_message,
    ]);
  }

}

/**
 * Invalidate all user TFA settings due to new real_aes encrypt plugin for TFA.
 */
function mass_utility_post_update_tfa_real_aes(array &$sandbox) {
  // DBTNG does not support expressions in delete queries.
  $sql = "DELETE FROM users_data WHERE LEFT(name, 4) = 'tfa_'";
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  \Drupal::database()->query($sql);
}

/**
 * Remove unused DB tables.
 */
function mass_utility_post_update_drop_unused_tables(array &$sandbox) {
  $tables = [
    'old_5e4ddf_url_alias',
    'old_8985c9menu_link_content',
    'old_8985c9menu_link_content_data',
    'old_e16344taxonomy_term__2780c8d622',
    'old_e16344taxonomy_term__414527a523',
    'old_e16344taxonomy_term__884e5f904c',
    'old_e16344taxonomy_term__aa515d1f03',
    'old_e16344taxonomy_term__field_sprite_name',
    'old_e16344taxonomy_term__field_sprite_type',
    'old_e16344taxonomy_term__parent',
    'old_e16344taxonomy_term_data',
    'old_e16344taxonomy_term_field_data',
  ];
  foreach ($tables as $table) {
    Drupal::database()->schema()->dropTable($table);
  }
}

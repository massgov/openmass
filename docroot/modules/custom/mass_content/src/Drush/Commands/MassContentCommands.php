<?php

namespace Drupal\mass_content\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\mass_fields\MassUrlReplacementService;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Mass Content drush commands.
 */
class MassContentCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    parent::__construct();
  }

  /**
   * Migrate date field values.
   *
   * @param string $type
   *   Type of node to update
   *   Argument provided to the drush command.
   * @param int $limit
   *   Number of nodes to process
   *   Argument provided to the drush command.
   *
   * @command mass-content:migrate-dates
   *
   * @usage mass-content:migrate-dates foo 5000
   *   foo is the type of node to update,
   *   5000 is the number of nodes that will be processed.
   */
  public function migrateDateFields(string $type = '', int $limit = 0) {
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

    // 1. Log the start of the script.
    $this->loggerChannelFactory->get('mass_content')->info('Update nodes batch operations start');

    // 2. Retrieve all nodes of this type.
    $storage = $this->entityTypeManager->getStorage('node');
    try {
      $query = $storage->getQuery();
      // Check the type of node given as argument, if not, set article as default.
      if (strlen($type) == 0) {
        $query->condition('type', ['advisory', 'binder', 'decision', 'executive_order', 'info_details', 'regulation', 'rules', 'news'], 'IN');
      }
      else {
        $query->condition('type', $type);
        $query->exists($date_fields[$type]);
      }
      if ($limit !== 0) {
        $query->range(0, $limit);
      }

      $nids = $query->accessCheck(FALSE)->execute();
    }
    catch (\Exception $e) {
      $this->output()->writeln($e);
      $this->loggerChannelFactory->get('mass_content')->error('Error found @e', ['@e' => $e]);
    }
    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($nids)) {
      $this->output()->writeln("Preparing batches for " . count($nids) . " nodes.");
      foreach ($nids as $nid) {
        // Prepare the operation. Here we could do other operations on nodes.
        $this->output()->writeln("Preparing batch: " . $batchId);
        $operations[] = [
          '\Drupal\mass_content\MassContentBatchManager::processNode',
          [
            $batchId,
            $storage->load($nid),
            t('Updating node @nid', ['@nid' => $nid]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }
    else {
      $this->logger()->warning('No nodes of this type @type', ['@type' => $type]);
    }
    // 4. Create the batch.
    $batch = [
      'title' => t('Updating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\mass_content\MassContentBatchManager::processNodeFinished',
    ];
    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice("Batch operations end.");
    // 7. Log some information.
    $this->loggerChannelFactory->get('mass_content')->info('Update batch operations end.');

  }

  /**
   * Migrate Service data.
   *
   * @param int $offset
   *   Offset number of node to start processing
   *   Argument provided to the drush command.
   * @param int $limit
   *   Number of nodes to process
   *   Argument provided to the drush command.
   *
   * @command mass-content:migrate-service
   *
   * @usage mass-content:migrate-service 1000 500
   *   1000 is the offset where to start processing.
   *   500 is the number of nodes that will be processed.
   */
  public function migrateServiceData(int $offset, int $limit = 500) {
    // 1. Log the start of the script.
    $this->logger()->info('Update nodes batch operations start');

    // 2. Retrieve all nodes of this type.
    $storage = $this->entityTypeManager->getStorage('node');
    try {
      $query = $storage->getQuery();
      $query->condition('type', 'service_page');
      $query->sort('nid');
      $query->range($offset, $limit);

      $nids = $query->accessCheck(FALSE)->execute();
    }
    catch (\Exception $e) {
      $this->output()->writeln($e);
      $this->logger()->error('Error found @e', ['@e' => $e->getMessage()]);
    }

    $now = new DrupalDateTime('now');
    $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('type', 'event')
      ->exists('field_event_ref_parents')
      ->condition('field_event_date', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');
    $res = $query->execute();
    $nodes = $storage->loadMultiple($res);
    $result = [];
    foreach ($nodes as $node) {
      foreach ($node->get('field_event_ref_parents')->getValue() as $target) {
        $t_node = $storage->load($target['target_id']);
        if ($t_node && $t_node->bundle() == 'service_page') {
          $result[] = $t_node->id();
        }
      }
    }
    $service_with_events = array_unique($result);

    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($nids)) {
      $this->output()->writeln("Preparing batches for " . count($nids) . " nodes.");
      foreach ($nids as $nid) {
        // Prepare the operation. Here we could do other operations on nodes.
        $this->output()->writeln("Preparing batch: " . $batchId);
        $operations[] = [
          '\Drupal\mass_content\MassContentBatchManager::processServiceNode',
          [
            $batchId,
            $storage->load($nid),
            $service_with_events,
            t('Updating node @nid', ['@nid' => $nid]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }
    else {
      $this->logger()->warning(dt('No nodes of this type @type to process', ['@type' => 'service_page']));
    }
    // 4. Create the batch.
    $batch = [
      'title' => t('Updating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\mass_content\MassContentBatchManager::processNodeFinished',
    ];
    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice("Batch operations end.");
    // 7. Log some information.
    $this->logger()->info('Update batch operations end.');
    \Drupal::getContainer()->get('plugin.cache_clearer')->clearCachedDefinitions();

    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
  }

  /**
   * Replaces raw URLs in content with the aliases.
   *
   * Use --simulate to get a report, and skip healing.
   *
   * @command ma:heal-raw-urls
   * @field-labels
   *   success: Success
   *   entity_type: Entity Type
   *   entity_bundle: Entity Bundle
   *   entity_id: Entity ID
   *   field_name: Field Name
   *   parent_id: Parent ID
   *   parent_type: Parent Type
   *   parent_bundle: Parent Bundle
   * @default-fields success,entity_type,entity_bundle,entity_id,field_name,parent_id,parent_type,parent_bundle
   * @aliases mru
   * @filter-default-field from_id
   */
  public function healRawLinkInContent($options = ['format' => 'table']): RowsOfFields {
    $rows = [];
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    $entity_names = ['node_type', 'paragraphs_type'];
    foreach ($entity_names as $entity_name) {
      if ($entity_name == 'node_type') {
        $entity_storage_name = 'node';
      }
      elseif ($entity_name == 'paragraphs_type') {
        $entity_storage_name = 'paragraph';
      }
      $types = $this->getEntityBundles($entity_name);
      foreach ($types as $type) {
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_storage_name, $type);
        foreach ($fields as $field_name => $definition) {
          switch ($definition->getType()) {
            case 'text_with_summary':
            case 'text_long':
              $query = $this->entityTypeManager->getStorage($entity_storage_name)->getQuery();
              $query->condition('type', $type);
              // Add a condition to filter entities with a specific textarea field containing "node/nid".
              $query->condition("$field_name.value", 'node/', 'CONTAINS');

              // Execute the query and get a list of entity IDs.
              $entity_ids = $query->execute();
              if ($entity_ids) {
                $entities = $this->entityTypeManager->getStorage($entity_storage_name)->loadMultiple($entity_ids);
                foreach ($entities as $entity) {
                  $changed = FALSE;
                  if ($entity instanceof Paragraph) {
                    if (Helper::isParagraphOrphan($entity)) {
                      continue;
                    }
                  }
                  $list = $entity->get($field_name);
                  foreach ($list as $delta => $item) {
                    $values[$delta] = $item->getValue();
                    $value = $item->getValue()['value'];
                    $dom = Html::load($value);
                    $xpath = new \DOMXPath($dom);
                    foreach ($xpath->query("//a[starts-with(@href, '/node/')  and translate(substring(@href, 7), '0123456789', '') = '']") as $element) {
                      $pattern = '/\d+/';
                      if (preg_match($pattern, $element->getAttribute('href'), $matches)) {
                        if ($nid = $matches[0]) {
                          $node = $this->entityTypeManager->getStorage('node')->load($nid);
                          if ($node) {
                            $alias = \Drupal::service('path_alias.manager')->getAliasByPath($element->getAttribute('href'));
                            if ($alias) {
                              $changed = TRUE;
                              $href_url = parse_url($element->getAttribute('href'));
                              $anchor = empty($href_url["fragment"]) ? '' : '#' . $href_url["fragment"];
                              $query = empty($href_url["query"]) ? '' : '?' . $href_url["query"];
                              $element->setAttribute('data-entity-uuid', $node->uuid());
                              $element->setAttribute('data-entity-substitution', 'canonical');
                              $element->setAttribute('data-entity-type', 'node');
                              $element->setAttribute('href', $alias . $query . $anchor);
                            }
                          }
                        }
                      }
                    }

                    $replaced = Html::serialize($dom);
                    $values[$delta]['value'] = $replaced;
                  }
                  if ($changed) {
                    $result = [
                      'success' => 'No',
                      'entity_type' => $entity_storage_name,
                      'entity_bundle' => $type,
                      'entity_id' => $entity->id(),
                      'field_name' => $field_name,
                      'parent_id' => 'N/A',
                      'parent_type' => 'N/A',
                      'parent_bundle' => 'N/A',
                    ];
                    if (!Drush::simulate()) {
                      if (method_exists($entity, 'setRevisionLogMessage')) {
                        $entity->setNewRevision();
                        $entity->setRevisionLogMessage('Revision created to fix plain node links.');
                        $entity->setRevisionCreationTime(\Drupal::time()
                          ->getRequestTime());
                      }
                      $entity->set($field_name, $values);
                      $entity->save();
                      \Drupal::service('cache_tags.invalidator')->invalidateTags($entity->getCacheTagsToInvalidate());
                      $parent = $entity;
                      // Climb up to find a non-paragraph parent.
                      while (method_exists($parent, 'getParentEntity')) {
                        $parent = $parent->getParentEntity();
                        if (!$parent->isPublished()) {
                          continue 2;
                        }
                      }
                      $result['success'] = 'Yes';
                    }
                    else {
                      $parent = $entity;
                      // Climb up to find a non-paragraph parent.
                      while (method_exists($parent, 'getParentEntity')) {
                        $parent = $parent->getParentEntity();
                        if (!$parent->isPublished()) {
                          continue 2;
                        }
                      }
                      $result['parent_id'] = $parent->id();
                      $result['parent_type'] = $parent->getEntityTypeId();
                      $result['parent_bundle'] = $parent->bundle();
                    }
                    $rows[] = $result;
                  }
                }

              }
              break;
          }
        }
      }
    }
    return new RowsOfFields($rows);
  }

  /**
   * Populate contextual search related field default values.
   *
   * @command mass-content:populate-search-fields
   *
   * @aliases mpsf
   */
  public function populateContextualSearchFields() {
    // 1. Log the start of the script.
    $this->logger()->info('Update nodes batch operations start');

    // 2. Retrieve the nodes.
    $storage = $this->entityTypeManager->getStorage('node');

    $do_not_include_search_nids = [102151, 279281, 279391, 279421, 280801, 280841, 280891, 280911, 281166, 281201, 281276, 281411, 281456, 287771, 286861, 287811, 287841, 288221, 288231, 597581, 603051, 297131, 297136, 297146, 297166, 297176, 297226, 297236, 389236, 297506, 297546, 297716, 297726, 297741, 297761, 298191, 298201, 298261, 298271, 298291, 298511, 298516, 298616, 298791, 505031, 298881, 299621, 299616, 299511, 298531, 298536, 298541, 298546, 26951, 298551, 298556, 298561, 298571, 22331, 383086, 298581, 298586, 298591, 298211, 298216, 298221, 298226, 298231, 298236, 298241, 298246, 298251, 298256, 298186, 298006, 298011, 298026, 298031, 298036, 298041, 297791, 311511, 297801, 297811, 297806, 297816, 297826, 297831, 297836, 297841, 505026, 297671, 297666, 297661, 297651, 297631, 790306, 297616, 297601, 297591, 297301, 297446, 297441, 297431, 297421, 297416, 297411, 297401, 297396, 297246, 297391, 487206, 287871, 314251, 635851, 34161, 298566, 535211, 622806, 412921, 119191, 344641, 184756, 798351, 263216, 14066, 6096, 6516];
    $show_parent_nids = [705346, 408791, 790226, 342871, 514371, 415871, 685541, 468571, 22826, 51951, 418061, 64866, 571991, 646046, 790506, 14686, 683761, 13626, 47751, 528126, 15091, 74746, 629876, 609336, 71546, 144916, 71416, 74466, 6801, 511121, 13726, 409611, 507841, 74116, 13891, 205351, 27511, 488491];
    $nids = array_unique(array_merge($do_not_include_search_nids, $show_parent_nids));

    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($nids)) {
      $this->output()->writeln("Preparing batches for " . count($nids) . " nodes.");
      foreach ($nids as $nid) {
        if ($node = $storage->load($nid)) {
          $do_not_include = FALSE;
          $show_parent = FALSE;
          if (in_array($nid, $do_not_include_search_nids)) {
            $do_not_include = TRUE;
          }
          if (in_array($nid, $show_parent_nids)) {
            $show_parent = TRUE;
          }
          // Prepare the operation. Here we could do other operations on nodes.
          $this->output()->writeln("Preparing batch: " . $batchId);
          $operations[] = [
            '\Drupal\mass_content\MassContentBatchManager::processContextualSearchFields',
            [
              $batchId,
              $node,
              $do_not_include,
              $show_parent,
              t('Updating node @nid', ['@nid' => $nid]),
            ],
          ];
          $batchId++;
          $numOperations++;
        }
      }
    }
    else {
      $this->logger()->warning(dt('No nodes of this type @type to process', ['@type' => 'service_page']));
    }
    // 4. Create the batch.
    $batch = [
      'title' => t('Updating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\mass_content\MassContentBatchManager::processNodeFinished',
    ];
    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice("Batch operations end.");
    // 7. Log some information.
    $this->logger()->info('Update batch operations end.');

    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
  }

  /**
   * Get all content type bundle names.
   */
  public function getEntityBundles($type) {
    $bundles = [];

    // Get a list of all entity types.
    $content_types = $this->entityTypeManager->getStorage($type)->loadMultiple();

    // Iterate through entity types to retrieve bundle names.
    foreach ($content_types as $content_type) {
      $bundles[] = $content_type->id();
    }

    return $bundles;
  }

  /**
   * Search and replace media URLs.
   *
   * @command mass-content:search-replace-media
   *
   * @aliases msrm
   */
  public function searchAndReplaceMedia() {
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    $entityTypes = ['node', 'paragraph'];
    foreach ($entityTypes as $entityType) {
      // Retrieve the last processed ID from the state or default to 0
      $lastProcessedId = \Drupal::state()->get("mass_content.last_processed_id.{$entityType}", 0);

      // Determine the ID field name based on the entity type
      $idFieldName = $entityType === 'node' ? 'nid' : 'id';
      $storage = $this->entityTypeManager->getStorage($entityType);
      $ids = $storage->getQuery()
        ->condition($idFieldName, $lastProcessedId, '>')
        ->sort($idFieldName)
        ->accessCheck(FALSE)
        ->execute();

      $urlReplacementService = \Drupal::service('mass_fields.url_replacement_service');
      $changedEntities = 0;
      foreach ($ids as $id) {
        // After successfully processing the entity, update the last processed ID
        \Drupal::state()->set("mass_content.last_processed_id.{$entityType}", $id);
        $entity = $storage->load($id);
        if ($entity) {
          $changed = FALSE;
          foreach ($entity->getFields() as $field) {
            $fieldType = $field->getFieldDefinition()->getType();
            if (in_array($fieldType, ['text_long', 'text_with_summary', 'string_long'])) {
              foreach ($field as $item) {
                $processed = $urlReplacementService->processText($item->value);
                if ($processed['changed']) {
                  $item->value = $processed['text'];
                  $changed = TRUE;
                }
              }
            }
          }
          if ($changed) {
            if (method_exists($entity, 'setRevisionLogMessage')) {
              $entity->setNewRevision();
              $entity->setRevisionLogMessage('Revision created to fix raw media/ or files/ URL in the content.');
              $entity->setRevisionCreationTime(\Drupal::time()
                ->getRequestTime());
            }
            $entity->save();
            $changedEntities++;

            if ($entityType == 'paragraph') {
              $node = Helper::getParentNode($entity);
              $this->output()
                ->writeln(t('@type entity, Bundle @bundle with ID @id processed and saved. Appears on node: @nid', [
                  '@type' => ucfirst($entityType),
                  '@bundle' => $entity->bundle(),
                  '@id' => $entity->id(),
                  '@nid' => $node ? $node->id() : 'N/A',
                ]));
            }
            else {
              $this->output()
                ->writeln(t('@type entity, Bundle @bundle with ID @id processed and saved.', [
                  '@type' => ucfirst($entityType),
                  '@bundle' => $entity->bundle(),
                  '@id' => $entity->id()
                ]));
            }
          }
        }
      }

      $this->output()->writeln(t('Processed @count @type entities.', ['@count' => $changedEntities, '@type' => $entityType]));
    }
  }

}

<?php

namespace Drupal\mass_content\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Mass Content drush commands.
 */
class MassContentCommands extends DrushCommands {

  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
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

}

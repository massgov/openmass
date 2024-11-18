<?php

namespace Drupal\mass_content\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
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

  protected $externalDownloadMatch;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->externalDownloadMatch = '/(https:\/\/)(www.|)(mass.gov\/)(media\/([0-9]+)(\/download|\/|$)|files\/)/';
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
              $query->accessCheck(FALSE);
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
                    foreach ($xpath->query("//a[(starts-with(@href, 'node/') or starts-with(@href, '/node/')) and translate(substring-before(substring-after(@href, 'node/'), '?#'), '0123456789', '') = '']") as $element) {
                      $pattern = '/\d+/';
                      if (preg_match($pattern, $element->getAttribute('href'), $matches)) {
                        if ($nid = $matches[0]) {
                          $node = $this->entityTypeManager->getStorage('node')->load($nid);
                          if ($node) {
                            $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
                            if ($alias) {
                              if (!preg_match('/node\/\d+/', $alias)) {
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
      $changed_revisions = 0;
      foreach ($ids as $id) {
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
            elseif ($fieldType === 'link') {
              foreach ($field as $key => $item) {
                if ($item->uri) {
                  if (strpos($item->uri, 'internal:') === 0) {
                    $url = substr($item->uri, strlen('internal:'));
                    $processed = $urlReplacementService->processLink($url);
                    if ($processed['changed']) {
                      $item->value = 'internal:' . $processed['link'];
                      $changed = TRUE;
                    }
                  }
                  elseif (preg_match($this->externalDownloadMatch, $item->uri)) {
                    $processed = $urlReplacementService->processLink($item->uri);
                    if ($processed['changed']) {
                      $item->value = 'internal:' . $processed['link'];
                      $changed = TRUE;
                    }
                  }
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
              if ($node = Helper::getParentNode($entity)) {
                $node->setNewRevision();
                $node->setRevisionLogMessage('Revision created to fix raw media/ or files/ URL in the content.');
                $node->setRevisionCreationTime(\Drupal::time()
                  ->getRequestTime());
                $node->save();
              }
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
                  '@id' => $entity->id(),
                ]));
            }
          }
        }
        if (!$entity->isLatestRevision()) {
          $storage = \Drupal::entityTypeManager()->getStorage($entityType);
          $query = $storage->getQuery()->accessCheck(FALSE);
          $query->condition($idFieldName, $entity->id());
          $query->latestRevision();
          $rids = $query->execute();
          foreach ($rids as $rid => $value) {
            $latest_revision = $storage->loadRevision($rid);
            if (isset($latest_revision)) {
              $changed = FALSE;
              $entity = $latest_revision;
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
                elseif ($fieldType === 'link') {
                  foreach ($field as $key => $item) {
                    if ($item->uri) {
                      if (strpos($item->uri, 'internal:') === 0) {
                        $url = substr($item->uri, strlen('internal:'));
                        $processed = $urlReplacementService->processLink($url);
                        if ($processed['changed']) {
                          $item->value = 'internal:' . $processed['link'];
                          $changed = TRUE;
                        }
                      }
                      elseif (preg_match($this->externalDownloadMatch, $item->uri)) {
                        $processed = $urlReplacementService->processLink($item->uri);
                        if ($processed['changed']) {
                          $item->value = 'internal:' . $processed['link'];
                          $changed = TRUE;
                        }
                      }
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
                $changed_revisions++;

                if ($entityType == 'paragraph') {
                  if ($node = Helper::getParentNode($entity)) {
                    $node->setNewRevision();
                    $node->setRevisionLogMessage('Revision created to fix raw media/ or files/ URL in the content.');
                    $node->setRevisionCreationTime(\Drupal::time()
                      ->getRequestTime());
                    $node->save();
                  }
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
                      '@id' => $entity->id(),
                    ]));
                }
              }
            }
          }
        }
        // After successfully processing the entity, update the last processed ID
        \Drupal::state()->set("mass_content.last_processed_id.{$entityType}", $id);
      }

      $this->output()->writeln(t('Processed @count @type entities.', ['@count' => $changedEntities, '@type' => $entityType]));
      $this->output()->writeln(t('Processed @count @type entity revisions.', ['@count' => $changed_revisions, '@type' => $entityType]));
    }
  }

  /**
   * Processes 'service-details/[something]' links in all entities.
   *
   * Use --simulate to get a report, and skip healing.
   *
   * @command mass-content:process-service-details
   * @field-labels
   *   success: Success
   *   entity_type: Entity Type
   *   entity_id: Entity ID
   *   processed_links: Processed Links
   * @default-fields success,entity_type,entity_id,processed_links
   * @aliases mpsd
   * @option simulate If set, no changes will be saved.
   */
  public function processServiceDetails($options = ['simulate' => FALSE, 'format' => 'table']) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    $rows = [];
    $entityTypes = ['node', 'paragraph'];
    $urlReplacementService = \Drupal::service('mass_fields.url_replacement_service');

    foreach ($entityTypes as $entityType) {
      $lastProcessedId = 0;
      if (!$options['simulate']) {
        $lastProcessedId = \Drupal::state()
          ->get("mass_content.service_details.last_processed_id.{$entityType}", 0);
      }
      $idFieldName = $entityType === 'node' ? 'nid' : 'id';
      $storage = $this->entityTypeManager->getStorage($entityType);
      $ids = $storage->getQuery()
        ->condition($idFieldName, $lastProcessedId, '>')
        ->sort($idFieldName)
        ->accessCheck(FALSE)
        ->execute();

      $totalEntities = count($ids);
      $this->output()->writeln(t('Processing @count @type entities...', ['@count' => $totalEntities, '@type' => $entityType]));

      $changedEntities = 0;

      foreach ($ids as $id) {

        $entity = $storage->load($id);
        if ($entity) {
          $changed = $urlReplacementService->processServiceDetailsLink($entity);

          // Only output rows where changes will happen.
          if ($changed) {
            $row = [
              'success' => 'No',
              'entity_type' => $entityType,
              'entity_id' => $entity->id(),
              'processed_links' => $changed,
            ];

            $this->output()->writeln(t('Entity ID @id of @type has @changes changes.', [
              '@id' => $entity->id(),
              '@type' => $entityType,
              '@changes' => $changed,
            ]));

            if (!$options['simulate']) {
              if (method_exists($entity, 'setRevisionLogMessage')) {
                $entity->setNewRevision();
                $entity->setRevisionLogMessage('Revision created to update service-details links.');
                $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
              }
              $entity->save();
              $row['success'] = 'Yes';
              $changedEntities++;
            }
            else {
              $this->output()->writeln(t('Simulating: Entity @id changes not saved.', ['@id' => $entity->id()]));
            }

            $rows[] = $row;
          }

          if (!$options['simulate']) {
            // Update the last processed ID after each entity.
            \Drupal::state()
              ->set("mass_content.service_details.last_processed_id.{$entityType}", $id);
          }
        }
      }

      $this->output()->writeln(t('Processed @count @type entities with changes.', ['@count' => $changedEntities, '@type' => $entityType]));
    }

    if ($changedEntities > 0) {
      $this->output()->writeln(t('Finished processing all entities with changes. Exporting results...'));
    }
    else {
      $this->output()->writeln(t('No changes were detected during processing.'));
    }

    // Only return rows where changes happened.
    return new RowsOfFields($rows);
  }

  /**
   * Migrate section_long_form paragraphs to the new layout structure in info_details nodes.
   *
   * @command mass-content:migrate-layout-paragraphs
   * @option batch-size The number of nodes to process per batch.
   * @usage mass-content:migrate-layout-paragraphs --batch-size=50
   * @aliases mclp
   */
  public function migrateLayoutParagraphs($options = ['batch-size' => 50]) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    // Disable entity hierarchy writes for better performance during processing.
    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

    $batch_size = (int) $options['batch-size'];

    do {
      // Get the last processed nid from state.
      $last_processed_nid = \Drupal::state()->get('mass_content_deploy.info_details_migration_last_processed_nid', 0);

      // Query all nodes of type 'info_details' starting from the last processed nid.
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'info_details')
        ->condition('nid', $last_processed_nid, '>')
        ->accessCheck(FALSE)
        ->sort('nid')
        ->range(0, $batch_size);

      $nids = $query->execute();
      if (empty($nids)) {
        $this->output()->writeln('No more nodes to process.');
        break;
      }

      $node_storage = $this->entityTypeManager->getStorage('node');
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      $this->output()->writeln("Processing batch of " . count($nids) . " nodes...");

      foreach ($nids as $nid) {
        $node = $node_storage->load($nid);
        if (!$node) {
          continue;
        }

        $this->output()->writeln("Processing node {$nid}...");

        if ($node->hasField('field_info_details_sections')) {
          $sections = $node->get('field_info_details_sections')->referencedEntities();
          $new_sections = [];

          foreach ($sections as $section) {
            if ($section->bundle() === 'section_long_form') {
              // Check if heading is not hidden.
              if (!$section->get('field_hide_heading')->value) {
                // Create a new 'section_header' paragraph for the heading.
                $section_header_paragraph = $paragraph_storage->create([
                  'type' => 'section_header',
                  'field_section_long_form_heading' => $section->get('field_section_long_form_heading')->value,
                ]);
                $section_header_paragraph->save();
                $new_sections[] = [
                  'target_id' => $section_header_paragraph->id(),
                  'target_revision_id' => $section_header_paragraph->getRevisionId(),
                ];
              }

              // Migrate each content paragraph in field_section_long_form_content.
              $content_paragraphs = $section->get('field_section_long_form_content')->referencedEntities();
              foreach ($content_paragraphs as $content_paragraph) {
                $new_sections[] = [
                  'target_id' => $content_paragraph->id(),
                  'target_revision_id' => $content_paragraph->getRevisionId(),
                ];
              }
            }
          }

          // Update the node with the newly structured paragraphs.
          $node->set('field_info_details_sections', $new_sections);
          if (method_exists($node, 'setRevisionLogMessage')) {
            $node->setNewRevision();
            $node->setRevisionLogMessage('Revision created for layout paragraphs.');
            $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          }
          $node->save();

          // Update the last processed nid in the state to allow resuming from this point if needed.
          \Drupal::state()->set('mass_content_deploy.info_details_migration_last_processed_nid', $nid);

          $this->output()->writeln("Node {$nid} processed successfully.");
        }
      }

    } while (!empty($nids));

    // Re-enable entity hierarchy writes after processing.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);

    $this->output()->writeln("All nodes processed. Last processed id is: " . \Drupal::state()->get('mass_content_deploy.info_details_migration_last_processed_nid'));

    // Reset the last processed nid in state after completion.
    \Drupal::state()->delete('mass_content_deploy.info_details_migration_last_processed_nid');
  }

}

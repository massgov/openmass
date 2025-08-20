<?php

namespace Drupal\mass_content\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
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
   * Migrate section_long_form paragraphs to the new layout structure in info_details nodes.
   *
   * @command mass-content:migrate-layout-paragraphs
   * @option batch-size The number of nodes to process per batch.
   * @option limit The maximum number of nodes to process in this execution. If not set, process all nodes.
   * @option max-runtime The maximum runtime (in minutes) for this command. If not set, process indefinitely
   * @usage mass-content:migrate-layout-paragraphs --batch-size=50 --max-runtime=55
   * @usage mass-content:migrate-layout-paragraphs --batch-size=50 --limit=1000
   * @aliases mcsplp
   */
  public function migrateServiceSectionLayoutParagraphs($options = ['batch-size' => 50, 'limit' => NULL, 'max-runtime' => NULL, 'detailed-verbalization' => FALSE]) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $log_marker_node = '[LP-MIGRATED-NODE]';
    $log_marker_rev = '[LP-MIGRATED-REV]';

    // Disable entity hierarchy writes for better performance during processing.
    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

    $batch_size = (int) $options['batch-size'];
    $limit = isset($options['limit']) ? (int) $options['limit'] : NULL;
    // Default to 55 minutes.
    $max_runtime = isset($options['max-runtime']) ? (int) $options['max-runtime'] : NULL;
    // Use a unique state key based on the published/unpublished condition.
    $state_key = 'mass_content_deploy.service_page_migration_last_processed_nid';

    // Get the last processed nid for the selected state key.
    $last_processed_nid = \Drupal::state()->get($state_key, 0);

    $total_nodes = count($this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'service_page')
      ->condition('nid', $last_processed_nid, '>')
      ->accessCheck(FALSE)->execute());

    // Record the start time.
    $start_time = time();
    $batch_step = 0;
    $processed_nodes = 0;
    $processed_revisions = 0;
    do {

      // Check if max runtime has been exceeded (if set).
      if ($max_runtime !== NULL && (time() - $start_time) >= $max_runtime * 60) {
        $this->output()->writeln("Max runtime of {$max_runtime} minutes reached. Stopping execution.");
        break;
      }

      // If not processing a specific nid, get the batch as usual.
      // Get the last processed nid from state.
      $last_processed_nid = \Drupal::state()->get($state_key, 0);

      // Query all nodes of type 'service_page' starting from the last processed nid.
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'service_page')
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

      $this->output()->writeln("Processing batch of " . $batch_size * $batch_step . "-" . ($batch_size * $batch_step + $batch_size) . " nodes from $total_nodes");

      // Choose the nodes to process for this batch.
      foreach ($nids as $nid) {
        $revision_to_restore = NULL;

        $query = $node_storage->getQuery()->accessCheck(FALSE);
        $query->condition('nid', $nid);
        $query->latestRevision();
        $rids = $query->execute();
        if ($rids) {
          $rid = array_key_first($rids);
          $latest_revision = $node_storage->loadRevision($rid);
          if ($latest_revision) {
            if (!$latest_revision->isPublished() && !$latest_revision->isDefaultRevision()) {
              $existing_rev_log = method_exists($latest_revision, 'getRevisionLogMessage') ? (string) $latest_revision->getRevisionLogMessage() : '';
              if (str_contains($existing_rev_log, $log_marker_rev)) {
                if ($options['detailed-verbalization']) {
                  $this->output()->writeln("⏭️  Skipping unpublished non-default revision {$rid} of node {$nid} (already migrated).");
                }
              }
              else {
                if ($options['detailed-verbalization']) {
                  $this->output()->writeln("Processing revision {$rid} of node {$nid}.");
                }
                $revision_to_restore = clone $latest_revision;
                if ($revision_to_restore->hasField('field_service_sections')) {
                  $revision_to_restore = $this->serviceSectionLayoutParagraphHelper($revision_to_restore);
                  $processed_revisions++;
                  if ($options['detailed-verbalization']) {
                    $this->output()->writeln("Processed revision: {$rid} of node: {$nid}");
                  }
                }
              }
            }
          }
        }

        $node = $node_storage->load($nid);
        if (!$node) {
          continue;
        }

        // Skip already-processed default revision by checking the unique log marker.
        $existing_node_log = method_exists($node, 'getRevisionLogMessage') ? (string) $node->getRevisionLogMessage() : '';
        if (str_contains($existing_node_log, $log_marker_node)) {
          if ($options['detailed-verbalization']) {
            $this->output()->writeln("⏭️  Skipping node {$nid} (already migrated).");
          }
          \Drupal::state()->set($state_key, $nid);
          continue;
        }

        if ($options['detailed-verbalization']) {
          $this->output()->writeln("Processing node {$nid}...");
        }

        if ($node->hasField('field_service_sections')) {

          $entity = $this->serviceSectionLayoutParagraphHelper($node);

          // Validate field_service_sections references before save.
          foreach ($entity->get('field_service_sections') as $delta => $item) {
            $paragraph = $item->entity;
            if (!$paragraph || !$paragraph->getRevisionId()) {
              $this->output()->writeln("❌ Invalid or missing paragraph reference at delta {$delta} (target_id: {$item->target_id}, target_revision_id: {$item->target_revision_id})");
            }
          }

          // Rebuild LayoutParagraphsLayout to flush stale component metadata.
          $layout = new LayoutParagraphsLayout($entity->get('field_service_sections'));
          foreach ($layout->getComponents() ?? [] as $component) {
            $paragraphEntity = $component->getEntity();
            $this->logger()->info("✅ Layout component - Paragraph ID: {$paragraphEntity->id()}, Revision ID: {$paragraphEntity->getRevisionId()}, Parent UUID: {$component->getSetting('parent_uuid')}");
          }

          // Explicitly set a new revision and add a revision log message.
          $entity->setNewRevision(TRUE);
          $entity->setRevisionLogMessage('Migrated layout paragraphs ' . $log_marker_node);
          $entity->save();

          $processed_nodes++;
          // Update the state with the last processed nid for the current context.
          \Drupal::state()->set($state_key, $nid);

          if ($options['detailed-verbalization']) {
            $this->output()->writeln("Node {$nid} processed successfully.");
          }
        }

        // After saving the published node, if there was an unpublished, non-default revision previously processed,
        // restore it as the default revision.
        if (isset($revision_to_restore)) {
          // Rebuild LayoutParagraphsLayout to flush stale component metadata for the revision.
          $layout = new LayoutParagraphsLayout($revision_to_restore->get('field_service_sections'));
          foreach ($layout->getComponents() ?? [] as $component) {
            $paragraphEntity = $component->getEntity();
            $this->logger()->info("✅ Layout component - Paragraph ID: {$paragraphEntity->id()}, Revision ID: {$paragraphEntity->getRevisionId()}, Parent UUID: {$component->getSetting('parent_uuid')}");
          }
          $revision_to_restore->setNewRevision(TRUE);
          $revision_to_restore->isDefaultRevision(TRUE);
          $revision_to_restore->setRevisionLogMessage('Migrated layout paragraphs (restored revision) ' . $log_marker_rev);
          $revision_to_restore->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $revision_to_restore->setChangedTime(\Drupal::time()->getRequestTime());
          // Make sure at least one field has changed.
          $revision_to_restore->setTitle($revision_to_restore->getTitle() . " ");
          $revision_to_restore->save();
          if ($options['detailed-verbalization']) {
            $this->output()->writeln("Restored and set new default revision for node {$nid}.");
          }
        }

        // Stop processing if the total processed nodes exceed the limit (if set).
        if ($limit !== NULL && $processed_nodes >= $limit) {
          $this->output()->writeln("Reached the defined limit of {$limit} nodes. Stopping execution.");
          // Exit both foreach and do-while loop.
          break 2;
        }
      }

      $batch_step++;

    } while (!empty($nids));

    // Re-enable entity hierarchy writes after processing.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);

    // Delete paragraphs that were marked for deletion in tempstore.
    $tempstore = \Drupal::service('tempstore.private')->get('mass_content');
    $paragraphs_to_delete = $tempstore->get('paragraphs_to_delete') ?? [];
    foreach (array_unique($paragraphs_to_delete) as $original_id) {
      if (!$original_id) {
        continue;
      }
      $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($original_id);
      if ($paragraph) {
        $paragraph->delete();
        if ($options['detailed-verbalization']) {
          $this->output()->writeln("Paragraph has been deleted {$original_id}.");
        }
      }
    }
    $paragraph_ids = array_unique($tempstore->get('paragraphs_to_delete') ?? []);

    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $remaining = array_filter($paragraph_ids, function ($id) use ($paragraph_storage) {
      return (bool) $paragraph_storage->load($id);
    });

    if (empty($remaining)) {
      $tempstore->delete('paragraphs_to_delete');
    }

    $this->output()->writeln("Processed a total of {$processed_nodes} nodes. Last processed nid is: " . \Drupal::state()->get($state_key));
    $this->output()->writeln(t('Processed @count latest revisions.', ['@count' => $processed_revisions]));
  }

  /**
   * Transforms service section layout paragraphs into Layout Paragraphs format.
   *
   * This function modifies the layout structure of a given entity's
   * `field_service_sections` by:
   * - Converting `service_section` and `key_message_section` paragraphs into
   *   Layout Paragraphs containers with `onecol_mass` layout.
   * - Duplicating and reparenting each referenced child paragraph from
   *   `field_service_section_content` into the Layout Paragraphs structure under
   *   their section parent in the `content` region.
   * - Migrating section headings into new `section_header` paragraphs based on
   *   a style/visibility/column configuration matrix.
   * - Clearing out old heading values to avoid duplication where necessary.
   * - Ensuring all updated paragraphs are registered as layout components.
   *
   * This function assumes that the entity being passed has a
   * `field_service_sections` field and its referenced paragraphs follow the
   * legacy structure.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node or parent entity containing the `field_service_sections` field.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The updated entity with its service sections restructured for Layout Paragraphs.
   */
  public function serviceSectionLayoutParagraphHelper($entity) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph_field = $entity->get('field_service_sections');
    $layout = new LayoutParagraphsLayout($paragraph_field);
    $tempstore = \Drupal::service('tempstore.private')->get('mass_content');

    foreach ($paragraph_field->referencedEntities() as $section_paragraph) {
      if ($section_paragraph->bundle() !== 'service_section') {
        $component = new LayoutParagraphsComponent($section_paragraph);
        $layout->setComponent($section_paragraph);
        $component->setSettings([
          'layout' => 'onecol_mass',
          'parent_uuid' => NULL,
          'region' => NULL,
        ]);
        continue;
      }

      $component = new LayoutParagraphsComponent($section_paragraph);
      $layout->setComponent($section_paragraph);
      $component->setSettings([
        'layout' => 'onecol_mass',
        'parent_uuid' => NULL,
        'region' => NULL,
      ]);

      // Initialize used_child_ids before processing children.
      $used_child_ids = [];
      if ($section_paragraph->hasField('field_service_section_content') && !$section_paragraph->get('field_service_section_content')->isEmpty()) {
        $field_items = $section_paragraph->get('field_service_section_content')->getValue();
        foreach (array_reverse($field_items) as $item) {
          $revision_id = $item['target_revision_id'] ?? NULL;
          if (!$revision_id) {
            continue;
          }

          $child_paragraph = $paragraph_storage->loadRevision($revision_id);
          if (!$child_paragraph) {
            continue;
          }

          $original_id = $child_paragraph->id();
          $to_delete = $tempstore->get('paragraphs_to_delete') ?? [];
          $to_delete[] = $original_id;
          $tempstore->set('paragraphs_to_delete', $to_delete);

          // Duplicating and inserting children into the layout, avoiding duplicates.
          $duplicated_child = $child_paragraph->createDuplicate();
          $duplicated_child->save();
          $duplicated_child_id = $duplicated_child->id();

          if (!isset($used_child_ids[$duplicated_child_id])) {
            $layout->insertAfterComponent($section_paragraph->uuid(), $duplicated_child);
            $child_component = new LayoutParagraphsComponent($duplicated_child);
            $child_component->setSettings([
              'parent_uuid' => $section_paragraph->uuid(),
              'region' => 'content',
            ]);
            $used_child_ids[$duplicated_child_id] = TRUE;
          }
          else {
            $this->output()->writeln("⚠ Skipping duplicate child paragraph $duplicated_child_id already used in layout.");
          }
        }
      }

      $style = $section_paragraph->get('field_section_style')->value;
      $hide = (int) $section_paragraph->get('field_hide_heading')->value;
      $columns = (int) $section_paragraph->get('field_two_column')->value;

      if ($style === 'simple' && $columns === 0 && $hide === 0) {
        $header = $paragraph_storage->create([
          'type' => 'section_header',
          'field_section_long_form_heading' => $section_paragraph->get('field_service_section_heading')->value,
        ]);
        $header->save();

        $layout->insertAfterComponent($section_paragraph->uuid(), $header);
        $header_component = new LayoutParagraphsComponent($header);
        $header_component->setSettings([
          'parent_uuid' => $section_paragraph->uuid(),
          'region' => 'content',
        ]);

        $section_paragraph->set('field_service_section_heading', '');
      }
      elseif (
        ($style === 'simple' && $columns === 0 && $hide === 1) ||
        ($style === 'enhanced' && $hide === 1)
      ) {
        $section_paragraph->set('field_service_section_heading', '');
      }

      $section_paragraph->save();
    }

    $new_sections = [];
    foreach ($layout->getComponents() as $component) {
      $paragraph = $component->getEntity();
      $paragraph->save();
      $new_sections[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    // Diagnostic block to check for duplicate references.
    $seen = [];
    foreach ($new_sections as $ref) {
      $key = $ref['target_id'] . ':' . $ref['target_revision_id'];
      if (isset($seen[$key])) {
        $this->output()->writeln("❗ Duplicate paragraph reference in new_sections: $key");
      }
      $seen[$key] = TRUE;
    }
    $entity->set('field_service_sections', $new_sections);

    return $entity;
  }

}

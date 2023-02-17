<?php

namespace Drupal\mass_content\Commands;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drush\Commands\DrushCommands;

/**
 * Mass Content drush commands.
 */
class MassContentCommands extends DrushCommands {

  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

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
      'news' => 'field_news_date'
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

      $nids = $query->execute()->accessCheck(FALSE);
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

      $nids = $query->execute()->accessCheck(FALSE);
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

}

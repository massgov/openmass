<?php

namespace Drupal\mass_auto_parents\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MassAutoParentsQueueWorker class.
 *
 * A worker plugin to consume items from "mass_auto_parents_queue"
 * and assign parent relationships.
 *
 * @QueueWorker(
 *   id = "mass_auto_parents_queue",
 *   title = @Translation("Mass Auto Parents Queue"),
 *   cron = {"time" = 60}
 * )
 */
class MassAutoParentsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('mass_auto_parents');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $memory_cache = \Drupal::service('entity.memory_cache');
    // $data here is expected to contain child_nid and parent_nid.
    if (empty($data['child_nid']) && empty($data['parent_nid'])) {
      // Just skip this item.
      return;
    }
    $node_storage = $this->entityTypeManager->getStorage('node');
    if ($node = $node_storage->load($data['child_nid'])) {
      // Skip if the child is an org_page and the parent is not.
      if ($node->bundle() === 'org_page' && $data['parent_type'] !== 'org_page') {
        return;
      }
      if (!$node->get('field_primary_parent')->isEmpty()) {
        return;
      }
      try {
        // Set the field value.
        $field_value = [
          'target_id' => $data['parent_nid'],
          'weight' => 0,
        ];
        $node->set('field_primary_parent', $field_value);
        // Save the node.
        // Save without updating the last modified date. This requires a core patch
        // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
        $node->setSyncing(TRUE);
        $node->save();
        $memory_cache->deleteAll();
      }
      catch (\Exception $e) {
        $this->logger->warning("An error occurred when assigning parent relationship for entity with data: @data. Error message: {$e->getMessage()}", ['@data' => json_encode($data)]);
      }
    }
  }

}

<?php

namespace Drupal\mass_entity_usage\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\entity_usage\EntityUpdateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Database\Connection;

/**
 * Processes the entity usage tracking via a queue.
 *
 * @QueueWorker(
 *   id = "entity_usage_tracker",
 *   title = @Translation("Entity usage tracker"),
 *   cron = {"time" = 300}
 * )
 */
class EntityUsageTracker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity usage update manager.
   *
   * @var \Drupal\entity_usage\EntityUpdateManager
   */
  protected $entityUsageUpdateManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\entity_usage\EntityUpdateManager $entity_usage_update_manager
   *   Entity usage update manager.
   * @param \Drupal\Core\Database\Connection $database_service
   *   The Drupal Database service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityUpdateManager $entity_usage_update_manager, Connection $database_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityUsageUpdateManager = $entity_usage_update_manager;
    $this->database = $database_service;
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
      $container->get('entity_usage.entity_update_manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    $storage = $this->entityTypeManager->getStorage($data['entity_type']);

    if (!$storage) {
      return;
    }

    $entity = $storage->load($data['entity_id']);

    if (!$entity) {
      return;
    }

    switch ($data['operation']) {
      case 'insert':
      case 'update':
        $this->entityUsageUpdateManager->trackUpdateOnCreation($entity);
        if ($entity instanceof RevisionableInterface) {
          // Delete records from non-current revision ids.
          $this->database
            ->delete('entity_usage')
            ->condition('source_type', $entity->getEntityTypeId())
            ->condition('source_id', $entity->id())
            ->condition('source_vid', $entity->getRevisionId(), '<>')
            ->execute();
        }
        break;

      case 'predelete':
        $this->entityUsageUpdateManager->trackUpdateOnDeletion($entity);
        break;

      case 'translation_delete':
        $this->entityUsageUpdateManager->trackUpdateOnDeletion($entity, 'translation');
        break;

      case 'revision_delete':
        $this->entityUsageUpdateManager->trackUpdateOnDeletion($entity, 'revision');
        break;
    }
  }

}

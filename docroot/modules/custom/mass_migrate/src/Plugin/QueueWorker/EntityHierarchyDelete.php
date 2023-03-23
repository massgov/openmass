<?php

namespace Drupal\mass_migrate\Plugin\QueueWorker;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\entity_hierarchy\Storage\InsertPosition;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorage;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\entity_hierarchy\Storage\ParentEntityDeleteUpdater;
use Drupal\entity_hierarchy\Storage\TreeLockTrait;
use PNX\NestedSet\Node;
use PNX\NestedSet\NodeKey;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes the entity hierarchy tracking via a queue.
 *
 * @QueueWorker(
 *   id = "entity_hierarchy_delete",
 *   title = @Translation("Entity Hierarchy delete"),
 *   cron = {"time" = 300}
 * )
 */
class EntityHierarchyDelete extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (isset($data['entity'])) {
      $entity = $data['entity'];
      try {
        \Drupal::service('class_resolver')->getInstanceFromDefinition(ParentEntityDeleteUpdater::class)
          ->moveChildren($entity);
      }
      catch (\Exception $exception) {
        // Re-adding to the queue to try again later.
        \Drupal::queue('entity_hierarchy_delete')->createItem([
          'entity' => $entity,
        ]);
        $id = $entity->id();
        throw new \Exception("Unable to move children for entity: $id");
      }
    }
  }

}

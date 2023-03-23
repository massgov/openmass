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
 *   id = "entity_hierarchy_tracker",
 *   title = @Translation("Entity Hierarchy tracker"),
 *   cron = {"time" = 300}
 * )
 */
class EntityHierarchyTracker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use TreeLockTrait;

  /**
   * {@inheritdoc}
   */
  public NestedSetNodeKeyFactory $nodeKeyFactory;

  /**
   * {@inheritdoc}
   */
  public NestedSetStorageFactory $nestedSetStorageFactory;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $node_key_factory
   *   The factory.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nested_set_storage_factory
   *   The storage factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NestedSetNodeKeyFactory $node_key_factory, NestedSetStorageFactory $nested_set_storage_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeKeyFactory = $node_key_factory;
    $this->nestedSetStorageFactory = $nested_set_storage_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('entity_hierarchy.nested_set_storage_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data['operation'] == 'insert') {
      if (isset($data['field_item'])) {
        $this->fieldItem = $data['field_item'];
        // Get the field name.
        $fieldDefinition = $this->fieldItem->getFieldDefinition();
        $id = $this->fieldItem->getEntity()->id();
        $storage = $this->nestedSetStorageFactory->get($fieldDefinition->getName(), $fieldDefinition->getTargetEntityTypeId());

        $fieldName = $fieldDefinition->getName();
        $entityTypeId = $fieldDefinition->getTargetEntityTypeId();
        try {
          $this->lockTree($fieldName, $entityTypeId);
        } catch (\Exception $exception) {
          // Re-adding to the queue to try again later.
          \Drupal::queue('entity_hierarchy_tracker')->createItem([
            'operation' => 'insert',
            'field_item' => $this->fieldItem,
          ]);
          throw new \Exception("Unable to acquire lock to update tree. with the $fieldName with entity ID: $id");
        }
        // Get the parent/child entities and their node-keys in the nested set.
        $parentEntity = $this->fieldItem->get('entity')->getValue();
        if (!$parentEntity) {
          // Parent entity has been deleted.
          // If this node was in the tree, it needs to be moved to a root node.
          $stubNode = $this->nodeKeyFactory->fromEntity($this->fieldItem->getEntity());
          if (($existingNode = $storage->getNode($stubNode)) && $existingNode->getDepth() > 0) {
            $storage->moveSubTreeToRoot($existingNode);
          }
          $this->releaseLock($fieldName, $entityTypeId);
          Cache::invalidateTags($this->fieldItem->getEntity()->getCacheTags());
          return;
        }
        $parentKey = $this->nodeKeyFactory->fromEntity($parentEntity);
        $childEntity = $this->fieldItem->getEntity();
        $childKey = $this->nodeKeyFactory->fromEntity($childEntity);

        // Determine if this is a new node in the tree.
        $isNewNode = FALSE;
        if (!$childNode = $storage->getNode($childKey)) {
          $isNewNode = TRUE;
          // As we're going to be adding instead of
          // moving, a key is all we require.
          $childNode = $childKey;
        }

        // Does the parent already exist in the tree.
        if ($existingParent = $storage->getNode($parentKey)) {
          // If there are no siblings, we simply insert/move below.
          $insertPosition = new InsertPosition($existingParent, $isNewNode, InsertPosition::DIRECTION_BELOW);

          // But if there are siblings, we need to
          // ascertain the correct position in the order.
          if ($siblingEntities = $this->getSiblingEntityWeights($storage, $existingParent, $childNode)) {
            // Group the siblings by their weight.
            $weightOrderedSiblings = $this->fieldItem->groupSiblingsByWeight($siblingEntities, $fieldName);
            $weight = $this->fieldItem->get('weight')->getValue();
            $insertPosition = $this->fieldItem->getInsertPosition($weightOrderedSiblings, $weight, $isNewNode) ?: $insertPosition;
          }
          try {
            $insertPosition->performInsert($storage, $childNode);
          } catch (\Exception $exception) {
            // Re-adding to the queue to try again later.
            \Drupal::queue('entity_hierarchy_tracker')->createItem([
              'operation' => 'insert',
              'field_item' => $this->fieldItem,
            ]);
            throw new \Exception("Unable to perform insert to the hierarchy, field name: $fieldName with entity ID: $id");
          }
          $this->releaseLock($fieldName, $entityTypeId);
          Cache::invalidateTags($this->fieldItem->getEntity()->getCacheTags());
          return;
        }
        // We need to create a node for the parent in the tree.
        $parentNode = $storage->addRootNode($parentKey);
        try {
          (new InsertPosition($parentNode, $isNewNode, InsertPosition::DIRECTION_BELOW))->performInsert($storage, $childNode);
        } catch (\Exception $exception) {
          // Re-adding to the queue to try again later.
          \Drupal::queue('entity_hierarchy_tracker')->createItem([
            'operation' => 'insert',
            'field_item' => $this->fieldItem,
          ]);
          throw new \Exception("Unable to perform insert to the hierarchy, field name: $fieldName with entity ID: $id");
        }
        $this->releaseLock($fieldName, $entityTypeId);
        Cache::invalidateTags($this->fieldItem->getEntity()->getCacheTags());
      }
    }
    elseif ($data['operation'] == 'delete') {
      if (isset($data['entity'])) {
        $entity = $data['entity'];
        try {
          \Drupal::service('class_resolver')->getInstanceFromDefinition(ParentEntityDeleteUpdater::class)
            ->moveChildren($entity);
        }
        catch (\Exception $exception) {
          // Re-adding to the queue to try again later.
          \Drupal::queue('entity_hierarchy_tracker')->createItem([
            'operation' => 'delete',
            'entity' => $entity,
          ]);
          $id = $entity->id();
          throw new \Exception("Unable to move children for entity: $id");
        }
      }
    }
  }

  /**
   * Gets siblings.
   *
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorage $storage
   *   Storage.
   * @param \PNX\NestedSet\Node $parentNode
   *   Existing parent node.
   * @param \PNX\NestedSet\Node|\PNX\NestedSet\NodeKey $childNode
   *   Child node.
   *
   * @return \SplObjectStorage|bool
   *   Map of weights keyed by node or FALSE if no siblings.
   */
  protected function getSiblingEntityWeights(NestedSetStorage $storage, Node $parentNode, $childNode) {
    if ($siblingNodes = array_filter($storage->findChildren($parentNode->getNodeKey()), function (Node $node) use ($childNode) {
      if ($childNode instanceof NodeKey) {
        // Exclude self and all revisions.
        return $childNode->getId() !== $node->getNodeKey()->getId();
      }
      // Exclude self and all revisions.
      return $childNode->getNodeKey()->getId() !== $node->getNodeKey()->getId();
    })) {
      return $this->loadSiblingEntityWeights($siblingNodes);
    }
    return FALSE;
  }

  /**
   * Loads other children of the given parent.
   *
   * @param \PNX\NestedSet\Node[] $siblings
   *   Target siblings.
   *
   * @return \SplObjectStorage
   *   Map of weights keyed by node.
   */
  protected function loadSiblingEntityWeights(array $siblings) {
    $fieldDefinition = $this->fieldItem->getFieldDefinition();
    $entityType = \Drupal::entityTypeManager()->getDefinition($fieldDefinition->getTargetEntityTypeId());
    $entityTypeId = $fieldDefinition->getTargetEntityTypeId();
    $entityStorage = \Drupal::entityTypeManager()->getStorage($entityTypeId);
    $siblingEntities = new \SplObjectStorage();
    $key = $entityType->hasKey('revision') ? $entityType->getKey('revision') : $entityType->getKey('id');
    $parentField = $fieldDefinition->getName();
    $query = $entityStorage->getAggregateQuery();
    $ids = array_map(function (Node $item) {
      return $item->getRevisionId();
    }, $siblings);
    $entities = $query
      ->groupBy($key)
      ->sort($key, 'ASC')
      ->groupBy($parentField . '.weight')
      ->condition($key, $ids, 'IN')
      ->execute();
    $weightSeparator = $fieldDefinition instanceof BaseFieldDefinition ? '__' : '_';
    $entities = array_combine(array_column($entities, $key), array_column($entities, $parentField . $weightSeparator . 'weight'));
    foreach ($siblings as $node) {
      if (!isset($entities[$node->getRevisionId()])) {
        continue;
      }
      $siblingEntities[$node] = (int) $entities[$node->getRevisionId()];
    }

    return $siblingEntities;
  }

}

<?php

namespace Drupal\mass_microsites;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\node\NodeInterface;
use PNX\NestedSet\Node;

/**
 * Defines a class for looking up a microsite given a child node and parent nid.
 */
class ChildOfMicrositeLookup {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Nested set storage.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory
   */
  protected $nestedSetStorageFactory;

  /**
   * Nested set node key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeKeyFactory;

  /**
   * Constructs a new ChildOfMicrositeLookup.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nestedSetStorageFactory
   *   Storage factory.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nodeKeyFactory
   *   Key factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, NestedSetStorageFactory $nestedSetStorageFactory, NestedSetNodeKeyFactory $nodeKeyFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nestedSetStorageFactory = $nestedSetStorageFactory;
    $this->nodeKeyFactory = $nodeKeyFactory;
  }

  /**
   * Determine if the given child node, parent pair has a microsite.
   *
   * @param \Drupal\node\NodeInterface $child_node
   *   The child or leaf node.
   * @param int $parent_id
   *   The entity_id of the parent node.
   * @param string $field_name
   *   The field name that defines the entity hierarchy.
   *
   * @return bool
   *   If the given child node, parent pair has a microsite in the entity
   *   hierarchy defined by the given field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasMicrositeForChildNodeParentNid(NodeInterface $child_node, int $parent_id, string $field_name): bool {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $parent_node = $node_storage->load($parent_id);

    if (!$parent_node instanceof ContentEntityInterface) {
      return FALSE;
    }

    $key = $this->nodeKeyFactory->fromEntity($parent_node);
    /** @var \PNX\NestedSet\NestedSetInterface $nested_set_storage */
    $nested_set_storage = $this->nestedSetStorageFactory->get($field_name, 'node');
    $ids = array_map(function (Node $treeNode) {
      return $treeNode->getId();
    }, $nested_set_storage->findAncestors($key));

    $ids[] = $parent_node->id();
    $ids[] = $child_node->id();

    $eh_microsite_storage = $this->entityTypeManager->getStorage('entity_hierarchy_microsite');
    $microsite_ids = $eh_microsite_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('id')
      ->condition('home', array_unique($ids), 'IN')
      ->execute();
    return !empty($microsite_ids);
  }

}

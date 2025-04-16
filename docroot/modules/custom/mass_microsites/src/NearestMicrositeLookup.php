<?php

namespace Drupal\mass_microsites;

use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy_microsite\ChildOfMicrositeLookup;
use Drupal\node\NodeInterface;

/**
 * Finds the nearest microsite for a given node.
 */
class NearestMicrositeLookup {

  protected NestedSetStorageFactory $nestedSetStorageFactory;

  protected NestedSetNodeKeyFactory $nestedSetNodeKeyFactory;

  protected ChildOfMicrositeLookup $micrositeLookup;

  /**
   * Constructs a new NearestMicrositeLookup object.
   *
   * @param NestedSetStorageFactory $nested_set_storage_factory
   *   The nested set storage factory.
   * @param NestedSetNodeKeyFactory $nested_set_node_key_factory
   *   The nested set node key factory.
   * @param ChildOfMicrositeLookup $microsite_lookup
   *   The child of microsite lookup.
   */
  public function __construct(
    \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nested_set_storage_factory,
    \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nested_set_node_key_factory,
    \Drupal\entity_hierarchy_microsite\ChildOfMicrositeLookup $microsite_lookup,
  ) {
    $this->nestedSetStorageFactory = $nested_set_storage_factory;
    $this->nestedSetNodeKeyFactory = $nested_set_node_key_factory;
    $this->micrositeLookup = $microsite_lookup;
  }

  /**
   * Select the nearest microsite based on the node's hierarchy.
   *
   * @param \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface[] $microsites
   *   An array of microsites in which the node exists.
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface
   *   The microsite with the fewest pages between the current node and the microsite's "home" page.
   */
  public function selectNearestMicrosite(array $microsites, NodeInterface $node) {
    // The microsite for which the "homepage" is closest to the current node.
    /** @var \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface|null */
    $nearest_microsite = NULL;
    $microsites_by_home_id = [];

    foreach ($microsites as $microsite) {
      $microsites_by_home_id[$microsite->getHome()->id()] = $microsite;
    }

    if (count($microsites_by_home_id)) {
      $nestedSetStorage = $this->nestedSetStorageFactory->get('field_primary_parent', 'node');
      $key = $this->nestedSetNodeKeyFactory->fromEntity($node);

      // Array of ancestors in hierarchy, starting with field_primary_parent and climbing upward.
      /** @var \PNX\NestedSet\Node[] */
      $ancestors = array_reverse($nestedSetStorage->findAncestors($key));

      foreach ($ancestors as $ancestor) {
        $ancestor_id = $ancestor->getNodeKey()->getId();
        if (
          !$nearest_microsite &&
          isset($microsites_by_home_id[$ancestor_id])
        ) {
          $nearest_microsite = $microsites_by_home_id[$ancestor_id];
        }
      }
    }

    return $nearest_microsite;
  }

  /**
   * Get the nearest microsite for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface|null
   *   The nearest microsite, or NULL if no microsite is found.
   */
  public function getNearestMicrosite(NodeInterface $node) {
    if ($microsites = $this->micrositeLookup->findMicrositesForNodeAndField($node, 'field_primary_parent')) {
      return $this->selectNearestMicrosite($microsites, $node);
    }
  }

}

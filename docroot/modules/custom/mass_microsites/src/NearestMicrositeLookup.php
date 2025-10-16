<?php

namespace Drupal\mass_microsites;

use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\entity_hierarchy_microsite\ChildOfMicrositeLookup;
use Drupal\node\NodeInterface;

/**
 * Finds the nearest microsite for a given node.
 */
class NearestMicrositeLookup {

  protected QueryBuilderFactory $queryBuilderStorageFactory;

  protected ChildOfMicrositeLookup $micrositeLookup;

  /**
   * Constructs a new NearestMicrositeLookup object.
   *
   * @param QueryBuilderFactory $query_builder_storage_factory
   *   The nested set storage factory.
   * @param ChildOfMicrositeLookup $microsite_lookup
   *   The child of microsite lookup.
   */
  public function __construct(
    \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $query_builder_storage_factory,
    \Drupal\entity_hierarchy_microsite\ChildOfMicrositeLookup $microsite_lookup,
  ) {
    $this->queryBuilderStorageFactory = $query_builder_storage_factory;
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

    if (!empty($microsites_by_home_id)) {
      $queryBuilderStorage = $this->queryBuilderStorageFactory->get('field_primary_parent', 'node');

      // Ancestors ordered nearest-first: parent (field_primary_parent) up to root.
      $ancestors = array_reverse($queryBuilderStorage->findAncestors($node));
      if (!empty($ancestors)) {
        foreach ($ancestors as $ancestor) {
          /** @var \Drupal\entity_hierarchy\Storage\Record $ancestor */
          $ancestor_id = $ancestor->getId();
          if (isset($microsites_by_home_id[$ancestor_id])) {
            // Immediate bailout: the first match is the nearest microsite.
            return $microsites_by_home_id[$ancestor_id];
          }
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
    static $nearestCache = [];

    $nid = (int) $node->id();
    if (isset($nearestCache[$nid])) {
      return $nearestCache[$nid];
    }

    $result = NULL;
    $microsites = $this->micrositeLookup->findMicrositesForNodeAndField($node, 'field_primary_parent');
    if (!empty($microsites)) {
      $result = $this->selectNearestMicrosite($microsites, $node);
    }

    // Cache the result (including NULL) for subsequent calls in the same request.
    $nearestCache[$nid] = $result;
    return $result;
  }

}

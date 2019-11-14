<?php

namespace Drupal\mass_content_api;

use Drupal\node\Entity\Node;

/**
 * Interface DescendantManagerInterface.
 *
 * @package Drupal\mass_content_api
 */
interface DescendantManagerInterface {

  const MAX_DEPTH = 6;

  /**
   * Sets parent / child relationships based on a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to be used to discover relationships.
   */
  public function index(Node $node): void;

  /**
   * Remove relationship data for a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to be remove from the index.
   */
  public function deindex(Node $node): void;

  /**
   * Get parents of a given node.
   *
   * @param int $node_id
   *   The child NID.
   * @param int $depth
   *   The maximum depth to fetch.  May not be deeper than MAX_DEPTH.
   *
   * @return array
   *   An level-indexed array of parents.
   */
  public function getParents(int $node_id, $depth = self::MAX_DEPTH): array;

  /**
   * Get the child nodes of a given node, returned in a flattened array.
   *
   * @param int $node_id
   *   The ID of the node.
   * @param int $depth
   *   The maximum depth to fetch.  May not be deeper than MAX_DEPTH.
   *
   * @return int[]
   *   An array of child IDs.
   */
  public function getChildrenFlat(int $node_id, $depth = self::MAX_DEPTH): array;

  /**
   * Get children of a given node returned in a level-indexed array.
   *
   * @param int $node_id
   *   The parent's NID.
   * @param int $depth
   *   The maximum depth to fetch.  May not be deeper than MAX_DEPTH.
   *
   * @return array
   *   The children of the node, in a level-indexed array.
   */
  public function getChildrenLeveled(int $node_id, $depth = self::MAX_DEPTH);

  /**
   * Get the child nodes of a given node, returned in a tree structure.
   *
   * @param int $node_id
   *   The ID of the node.
   * @param int $depth
   *   The maximum depth to fetch.  May not be deeper than MAX_DEPTH.
   *
   * @return array
   *   The children of the node, in a tree array.
   */
  public function getChildrenTree(int $node_id, $depth = self::MAX_DEPTH);

  /**
   * Get all linked entities by parent entity id and parent entity type.
   *
   * @param int $id
   *   The id of the entity to be used to discover relationships.
   * @param string $entity_type
   *   The entity type of the parent entity.
   *
   * @return int[]
   *   Impact ids.
   */
  public function getImpact(int $id, string $entity_type);

  /**
   * Get the organizations related to a given node.
   *
   * @param int $node_id
   *   The id of the node.
   *
   * @return array
   *   An array of ids of related organizations.
   */
  public function getOrganizations(int $node_id);

  /**
   * Get the services related to a given node.
   *
   * @param int $node_id
   *   The id of the node.
   *
   * @return array
   *   An array of ids of the related services.
   */
  public function getServices(int $node_id);

  /**
   * Get the topics stored by level.
   *
   * @param int $node_id
   *   The id of the node.
   *
   * @return array
   *   The topics of the node.
   */
  public function getTopics(int $node_id);

}

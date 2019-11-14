<?php

namespace Drupal\mass_content_api;

/**
 * Defines the interface for DM storage.
 */
interface DescendantStorageInterface {

  /**
   * Retrieve child records for a given set of parents.
   *
   * @param int[] $parentIds
   *   The array of node IDs to fetch children for.
   *
   * @return array
   *   An array of child records, keyed on ID.
   */
  public function getChildren(array $parentIds): array;

  /**
   * Retrieve parent records for a given set of children.
   *
   * @param int[] $childIds
   *   The array of node IDs to fetch parents for.
   *
   * @return array
   *   An array of parent records, keyed on ID.
   */
  public function getParents(array $childIds): array;

  /**
   * Retrieve an array of the pages that link to this one.
   *
   * @param string $entity_type
   *   The entity type to fetch links to.
   * @param int $entity_id
   *   The entity id to fetch links to.
   *
   * @return int[]
   *   An array of entity ids.
   */
  public function getLinksTo(string $entity_type, int $entity_id): array;

  /**
   * Note a parent/child relationship in the index.
   *
   * @param string $reporter_type
   *   Entity type of the entity this relation was discovered on.
   * @param int $reporter_id
   *   Entity id of the entity this relation was discovered on.
   * @param string $parent_type
   *   Entity type of the parent.
   * @param int $parent_id
   *   Entity id of the parent.
   * @param string $child_type
   *   Entity type of the child.
   * @param int $child_id
   *   Entity id of the child.
   */
  public function addParentChildRelation(string $reporter_type, int $reporter_id, string $parent_type, int $parent_id, string $child_type, int $child_id): void;

  /**
   * Note a linking page in the index.
   *
   * @param string $reporter_type
   *   Entity type of the entity this relation was discovered on.
   * @param int $reporter_id
   *   Entity id of the entity this relation was discovered on.
   * @param string $source_type
   *   Entity type of the source of the link.
   * @param int $source_id
   *   Entity id of the source of the link.
   * @param string $destination_type
   *   Entity type of the destination of the link.
   * @param int $destination_id
   *   Entity id of the destination of the link.
   */
  public function addLinkingPage(string $reporter_type, int $reporter_id, string $source_type, int $source_id, string $destination_type, int $destination_id): void;

  /**
   * Remove all relationships for a reporter from the index.
   *
   * @param string $reporter_type
   *   Entity type.
   * @param int $reporter_id
   *   Entity ID.
   */
  public function removeRelationships(string $reporter_type, int $reporter_id): void;

  /**
   * Add debug data for an entity.
   *
   * @param string $reporter_type
   *   Entity type.
   * @param int $reporter_id
   *   Entity ID.
   * @param float $time
   *   The seconds it took to index this node, expressed as a float.
   * @param object|array $debug
   *   Arbitrary debug data (will be serialized).
   */
  public function addDebug(string $reporter_type, int $reporter_id, float $time, $debug): void;

  /**
   * Remove debug data for an entity.
   *
   * @param string $reporter_type
   *   Entity type.
   * @param int $reporter_id
   *   Entity ID.
   */
  public function removeDebug(string $reporter_type, int $reporter_id): void;

}

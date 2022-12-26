<?php

namespace Drupal\mass_content_api;

use Drupal\node\Entity\Node;

/**
 * This is the Descendant Manager.
 *
 * The descendant manager tracks relationships between entities, and allows for
 * determining deeply nested relationships in a performant way.
 *
 * Architecturally, it's split into 3 parts: the storage (where the relationship
 * data is indexed to), the extractor (the part that determines what a given
 * entity is related to), and the manager (this class). This separation makes
 * it possible to unit test the component parts to make sure they fulfill
 * their individual duties.
 *
 * @package Drupal\mass_content_api
 */
class DescendantManager implements DescendantManagerInterface {

  /**
   * The storage.
   *
   * @var \Drupal\mass_content_api\DescendantStorageInterface
   */
  protected $storage;

  /**
   * The extractor.
   *
   * @var \Drupal\mass_content_api\DescendantExtractorInterface
   */
  protected $extractor;

  /**
   * Constructs a new DescendantManager object.
   */
  public function __construct(DescendantStorageInterface $storage, DescendantExtractorInterface $extractor) {
    $this->storage = $storage;
    $this->extractor = $extractor;
  }

  /**
   * {@inheritdoc}
   */
  public function index(Node $node): void {
    $start = microtime(TRUE);
    $this->storage->removeRelationships($node->getEntityTypeId(), $node->id());

    $descendants = $this->extractor->extract($node);
    // @todo Getting duplicate records from the extractor.  Does this matter?
    // @todo Rename 'entity' to 'entity_type' for clarity.
    foreach ($descendants as $dependency_status => $fields) {
      foreach ($fields as $field) {
        if ($dependency_status === 'parents') {
          foreach ($field as $field_info) {
            $this->storage->addParentChildRelation($node->getEntityTypeId(), $node->id(), $field_info['entity'], $field_info['id'], $node->getEntityTypeId(), $node->id());
          }
        }
        if ($dependency_status === 'children') {
          foreach ($field as $field_info) {
            $this->storage->addParentChildRelation($node->getEntityTypeId(), $node->id(), $node->getEntityTypeId(), $node->id(), $field_info['entity'], $field_info['id']);
          }
        }
        if ($dependency_status === 'linking_pages') {
          foreach ($field as $field_info) {
            $this->storage->addLinkingPage($node->getEntityTypeId(), $node->id(), $node->getEntityTypeId(), $node->id(), $field_info['entity'], $field_info['id']);
          }
        }
      }
    }
    $elapsed = microtime(TRUE) - $start;
    $this->storage->addDebug($node->getEntityTypeId(), $node->id(), $elapsed, $descendants);
  }

  /**
   * {@inheritdoc}
   */
  public function deindex(Node $node): void {
    $this->storage->removeRelationships($node->getEntityTypeId(), $node->id());
    $this->storage->removeDebug($node->getEntityTypeId(), $node->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getChildrenFlat(int $node_id, $depth = self::MAX_DEPTH): array {
    $children = $this->getChildrenLeveled($node_id, $depth);
    // Flatten the leveled array, then pick out the ID column.
    $unleveled = $children ? array_merge(...$children) : [];
    return $unleveled ? array_column($unleveled, 'id') : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChildrenTree(int $node_id, $depth = self::MAX_DEPTH): array {
    $leveled_descendants = $this->getChildrenLeveled($node_id, $depth);
    // What we get above is "descendants by level"
    // but the items are not yet in a "nested parent-child" structure.
    // We want to go from
    // FROM: descendants by level like this TODO
    // TO: nested parent child like this TODO
    // so we start iterating the levels from the deepest depth
    // level D, pluck each item from there, and attach it
    // as a child to the rightful parent at depth level D-1,
    // repeating until we reach the 1st level, which is
    // now our desired nested parent child output.
    $d = count($leveled_descendants);
    while ($d >= 1) {
      foreach ($leveled_descendants[$d] as $item_id => &$item) {
        // This ensures every item has a "children" key (even
        // if with empty value) because existing downstream functions
        // like `DescendantController::printChildrenDepth()`
        // depend on this key being present in the nested structure.
        // NOTE: We have $item populated by reference above.
        if (!isset($item['children'])) {
          $item['children'] = [];
        }

        if ($d >= 2) {
          $parent_id = $item['parent'];
          if (!isset($leveled_descendants[$d - 1][$parent_id]['children'])) {
            $leveled_descendants[$d - 1][$parent_id]['children'] = [];
          }
          $leveled_descendants[$d - 1][$parent_id]['children'][] = $item;
        }
      }
      $d--;
    }
    // To keep downstream functions from breaking we
    // return legitimate output or an empty array (instead of "null").
    $nested_parent_child_array = !empty($leveled_descendants[1]) ? array_values($leveled_descendants[1]) : [];
    return $nested_parent_child_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildrenLeveled(int $node_id, $depth = self::MAX_DEPTH) {
    // We no longer do static caching of DM results because it was determined
    // to be unhelpful for most use cases, and caused extra complication and
    // memory usage.
    return $this->fetchChildren($node_id, $depth);
  }

  /**
   * {@inheritdoc}
   */
  public function getImpact(int $id, string $entity_type) {
    return $this->storage->getLinksTo($entity_type, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getParents(int $node_id, $depth = self::MAX_DEPTH): array {
    // We no longer do static caching of DM results because it was determined
    // to be unhelpful for most use cases, and caused extra complication and
    // memory usage.
    return $this->fetchParents($node_id, $depth);
  }

  /**
   * Fetch all child nodes of a given node.
   *
   * @param int $id
   *   The parent NID.
   * @param int $depth
   *   The depth to fetch for.
   *
   * @return array
   *   An array of children, keyed by level.
   */
  private function fetchChildren(int $id, int $depth): array {
    $lastLevel = [$id];
    $i = 1;
    $ancestry = [];
    while ($lastLevel && $i <= $depth) {
      $ancestry[$i] = $this->storage->getChildren($lastLevel);
      $lastLevel = array_column($ancestry[$i], 'id');

      // We remove "circular references" before fetching children at the next depth level,
      // by only proceeding with those id's that are not already present in the ancestry array.
      $j = 1;
      while ($j < $i) {
        $earlierLevel = array_column($ancestry[$j], 'id');
        $lastLevel = array_diff($lastLevel, $earlierLevel);
        $j++;
      }
      $i++;
    }
    return array_filter($ancestry);
  }

  /**
   * Fetch parents of a given node, up to N levels deep.
   *
   * @param int $id
   *   The child NID.
   * @param int $depth
   *   The depth to fetch.
   *
   * @return array
   *   An array of parents, keyed by depth.
   */
  private function fetchParents(int $id, int $depth): array {
    $lastLevel = [$id];
    $i = 1;
    $ancestry = [];
    while ($lastLevel && $i <= $depth) {
      $ancestry[$i] = $this->storage->getParents($lastLevel);
      $lastLevel = array_column($ancestry[$i], 'id');
      $i++;
    }
    return array_filter($ancestry);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganizations(int $node_id): array {
    $orgs = [];

    foreach ($this->getParents($node_id) as $level) {
      foreach ($level as $ancestor) {
        if ($ancestor['type'] === 'org_page') {
          $orgs[$ancestor['id']] = $ancestor['id'];
        }
      }
    }
    return $orgs;
  }

  /**
   * {@inheritdoc}
   */
  public function getServices(int $node_id) {
    $services = [];
    // Flatten parents of service_page type into a single array.
    foreach ($this->getParents($node_id) as $level) {
      foreach ($level as $ancestor) {
        if ($ancestor['type'] === 'service_page') {
          $services[$ancestor['id']] = $ancestor['id'];
        }
      }
    }
    return $services;
  }

  /**
   * {@inheritdoc}
   */
  public function getTopics(int $node_id) {
    return array_filter(array_map(function ($level) {
      $matching = array_keys(array_filter($level, function ($ancestor) {
        return $ancestor['type'] === 'topic_page';
      }));
      return $matching ? array_combine($matching, $matching) : [];
    }, $this->getParents($node_id)));
  }

}

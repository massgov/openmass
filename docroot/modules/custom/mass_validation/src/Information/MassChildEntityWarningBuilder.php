<?php

namespace Drupal\mass_validation\Information;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Information\ChildEntityWarningBuilder;

/**
 * Defines a class for building a list of child entity warnings.
 */
class MassChildEntityWarningBuilder extends ChildEntityWarningBuilder {

  /**
   * Gets warning about child entities before deleting a parent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   Parent to be deleted.
   *
   * @return \Drupal\mass_validation\Information\MassChildEntityWarning[]
   *   Array of warning value objects.
   */
  public function buildChildEntityWarnings(ContentEntityInterface $parent) {
    $return = [];
    if ($fields = $this->parentCandidate->getCandidateFields($parent)) {
      $cache = new CacheableMetadata();
      foreach ($fields as $field_name) {
        /** @var \PNX\NestedSet\NestedSetInterface $storage */
        $storage = $this->nestedSetStorageFactory->get($field_name, $parent->getEntityTypeId());
        $nodeKey = $this->nodeKeyFactory->fromEntity($parent);
        $children = $storage->findChildren($nodeKey);
        if ($parent_node = $storage->findParent($nodeKey)) {
          $children[] = $parent_node;
        }
        $entities = $this->treeNodeMapper->loadAndAccessCheckEntitysForTreeNodes($parent->getEntityTypeId(), $children, $cache);
        $return[] = new MassChildEntityWarning($entities, $cache, $parent_node);
      }
    }
    return $return;
  }

}

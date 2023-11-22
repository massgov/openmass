<?php

namespace Drupal\mass_validation\Information;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Information\ChildEntityWarningBuilder;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;

/**
 * Defines a class for building a list of child entity warnings.
 */
class MassChildEntityWarningBuilder extends ChildEntityWarningBuilder {

  /**
   * Remove children that are not published.
   */
  private function removeUnpublished($children) {
    foreach ($children as $key => $child) {
      $nid = $child->getId();
      $node = Node::load($nid);
      if ($node) {
        $state = $node->moderation_state[0]->value;

        if ($state != MassModeration::PUBLISHED) {
          unset($children[$key]);
        }
      }
    }
    return $children;
  }

  /**
   * Gets warning about child entities before deleting a parent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   Parent to be deleted.
   * @param bool $removeUnpublished
   *   Ignores unpublished items.
   *
   * @return \Drupal\mass_validation\Information\MassChildEntityWarning[]
   *   Array of warning value objects.
   */
  public function buildChildEntityWarnings(ContentEntityInterface $parent, $removeUnpublished = FALSE) {
    $return = [];
    if ($fields = $this->parentCandidate->getCandidateFields($parent)) {
      $cache = new CacheableMetadata();
      foreach ($fields as $field_name) {
        /** @var \PNX\NestedSet\NestedSetInterface $storage */
        $storage = $this->nestedSetStorageFactory->get($field_name, $parent->getEntityTypeId());
        $nodeKey = $this->nodeKeyFactory->fromEntity($parent);
        $children = $storage->findChildren($nodeKey);
        $children = !$removeUnpublished ? $children : $this->removeUnpublished($children);
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

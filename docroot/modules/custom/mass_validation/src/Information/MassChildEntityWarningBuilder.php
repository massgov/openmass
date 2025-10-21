<?php

namespace Drupal\mass_validation\Information;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Information\ChildEntityWarningBuilder;
use Drupal\entity_hierarchy\Storage\Record;
use Drupal\entity_hierarchy\Storage\RecordCollectionCallable;
use Drupal\mass_content_moderation\MassModeration;

/**
 * Defines a class for building a list of child entity warnings.
 */
class MassChildEntityWarningBuilder extends ChildEntityWarningBuilder {

  /**
   * Remove children that are not published.
   */
  public static function removeUnpublished(Record $record) {
    if ($node = $record->getEntity()) {
      $state = $node->moderation_state[0]->value;
      if ($state == MassModeration::PUBLISHED) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Gets warning about child entities before deleting a parent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   Parent to be deleted.
   * @param bool $removeUnpublished
   *   Ignores unpublished items.
   *
   * @return array \Drupal\mass_validation\Information\MassChildEntityWarning[]
   *   Array of warning value objects.
   */
  public function buildChildEntityWarnings(ContentEntityInterface $entity, bool $removeUnpublished = FALSE) {
    $return = [];

    if ($fields = $this->parentCandidate->getCandidateFields($entity)) {
      $cache = new CacheableMetadata();
      foreach ($fields as $field_name) {
        $queryBuilder = $this->queryBuilderFactory->get($field_name, $entity->getEntityTypeId());
        $records = $queryBuilder->findChildren($entity)->buildTree()
          ->filter(RecordCollectionCallable::viewLabelAccessFilter(...))
          ->filter(static::removeUnpublished(...));
        if (empty($records)) {
          continue;
        }
        $parent = $queryBuilder->findParent($entity);
        $return[] = new MassChildEntityWarning($records, $cache, $parent);
      }
    }

    return $return;
  }

}

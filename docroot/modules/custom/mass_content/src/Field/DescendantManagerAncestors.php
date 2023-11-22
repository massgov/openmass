<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A computed field class for Ancestors.
 */
class DescendantManagerAncestors extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      /** @var \Drupal\mass_content_api\DescendantManagerInterface $descendantManager */
      $descendantManager = \Drupal::service('descendant_manager');
      $limit = $this->getSetting('limit') ?? FALSE;
      $level_limit = $this->getSetting('level_limit') ?? FALSE;
      $types = $this->getSetting('ancestor_allowed_types') ?? 'all';
      $ancestors = $descendantManager->getParents($entity->id());
      $delta = 0;
      foreach ($ancestors as $level => $level_ancestors) {
        if ($level_limit && $level >= $level_limit) {
          break;
        }
        foreach ($level_ancestors as $ancestor) {
          if ($types === 'all' || in_array($ancestor['type'], $types)) {
            $this->list[$delta] = $this->createItem($delta, ['target_id' => $ancestor['id']]);
            $delta++;
            if ($limit && $delta >= $limit) {
              break 2;
            }
          }
        }
      }
    }
  }

}

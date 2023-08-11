<?php

namespace Drupal\mass_entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Tracks usage of entities related in entity_reference_hierarchy fields.
 *
 * @EntityUsageTrack(
 *   id = "entity_reference_hierarchy",
 *   label = @Translation("Entity Reference Hierarchy"),
 *   description = @Translation("Tracks relationships created with 'Entity Reference Hierarchy' fields."),
 *   field_types = {"entity_reference_hierarchy"},
 * )
 */
class EntityReferenceHierarchy extends EntityUsageTrackBase {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item) {
    /** @var \Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchy $item */
    $item_value = $item->getValue();
    if (empty($item_value['target_id'])) {
      return [];
    }
    $target_type = $item->getFieldDefinition()->getSetting('target_type');

    // Only return a valid result if the target entity exists.
    if (!$this->entityTypeManager->getStorage($target_type)->load($item_value['target_id'])) {
      return [];
    }

    return [$target_type . '|' . $item_value['target_id']];
  }

}

<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * Related Locations computed field.
 *
 * This takes settings for fields and types of nodes that should be checked for
 * references to the current node.
 *
 * To utilize this field, implement hook_entity_bundle_field_info and add a
 * field to the $fields array. Use the following settings to setup the scope for
 * this to query:
 *   - linkFields
 *     This should be a link field, but can be a referenced structure to the
 *     link field that includes a paragraph field with '.entity.'.
 *     Example:
 *       ->setSetting('linkFields', [
 *         'field_service_key_info_links_6',
 *         'field_links_actions_3',
 *         'field_paragraph_card.entity.field_related_org',
 *       ])
 *   - referenceFields
 *     This should be an entity reference field, but can be a referenced
 *     structure to the field that includes a paragraph field with '.entity.'.
 *     Example:
 *       ->setSetting('referenceFields', [
 *         'field_service_ref_locations',
 *         'field_org_ref_locations',
 *       ])
 *   - types
 *     This allows for an array of node bundle machine names to be passed in.
 *     It limits the results to these node bundles.
 *     Example:
 *       ->setSetting('types', ['news', 'organization'])
 */
class RelatedLocations extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $items = [];
    $entity = $this->getEntity();

    if (!$entity->isNew()) {
      $types = $this->getSetting('ancestor_allowed_types') ?? 'all';
      $fields = $this->getSetting('ancestor_allowed_fields') ?? NULL;

      $parent_nids = $this->filterDescendantsByTypeNew($entity, $types, $fields);

      if (!empty($parent_nids)) {
        $parent_nodes = Node::loadMultiple($parent_nids);
        foreach ($fields as $field) {
          // Initialize these values for the current field.
          $nested_fields = [];
          $node_field = $field;
          // If the reference field is nested, we will use a different helper
          // method. Set the node field for the initial condition.
          if (strpos($field, '>') !== FALSE) {
            $nested_fields = explode('>', $field);
            $node_field = $nested_fields[0];
          }
          foreach ($parent_nodes as $parent_node) {
            if ($parent_node->hasField($node_field)) {
              // Use the correct helper method based on the number of reference
              // fields. This logic is specific for section fields.
              if (empty($nested_fields)) {
                // We check here to see if field has value to avoid broken links.
                if ($referenced_items = Helper::getReferencedEntitiesFromField($parent_node, $field)) {
                  $ref_count[$parent_node->id()] = count($referenced_items);
                }
              }
              else {
                // If the reference field is nested in a sections field, use a
                // different helper method.
                if ($referenced_items = Helper::getReferencedEntitiesFromSectionField($parent_node, $nested_fields)) {
                  $ref_count[$parent_node->id()] = count($referenced_items);
                }
              }
            }
          }
        }
        if (!empty($ref_count)) {
          if (count($ref_count) > 2) {
            $items = $this->sortParents($ref_count);
          }
          else {
            $items = array_keys($ref_count);
          }
        }
        // DP-16699: Sometime parent can be the same node,
        // filtering to resolve duplicates issue.
        $items = array_unique($items);
      }

      $delta = 0;
      if (!empty($items)) {
        foreach ($items as $item) {
          $this->list[$delta] = $this->createItem($delta, ['target_id' => $item]);
          $delta++;
        }
      }
    }
  }

  public function filterDescendantsByTypeNew(Node $entity, array $types, array $fields) {
    $location_id = $entity->id();
    $query = \Drupal::entityQuery('node');
    $query->condition('type', $types, 'IN');
    $andCondition = $query->andConditionGroup();
    foreach ($fields as $field) {
      $query_string = str_replace(">",".entity.",$field);
      $orCondition = $query->orConditionGroup();
      $orCondition->condition($query_string, $location_id);
      $andCondition->condition($orCondition);
    }
    $query->condition($andCondition);
    return $query->execute();
  }

  /**
   * Filter descendants by node type.
   *
   * @param \Drupal\node\Entity\Node $entity
   *   The entity to get descendants from.
   * @param array $types
   *   An array of node types to filter by.
   *
   * @return array
   *   An array of descendant node IDs.
   */
  private function filterDescendantsByType(Node $entity, array $types) {
    $nodes = [];

    $descendantManager = \Drupal::service('descendant_manager');
    $parents = $descendantManager->getParents($entity->id(), 1);

    if (is_array($parents) && array_key_exists(1, $parents)) {
      $nodes = array_filter($parents[1], function ($p) use ($types) {
        if ($types === 'all' || in_array($p['type'], $types)) {
          return $p;
        }
      });
    }

    return array_keys($nodes);
  }

  /**
   * Sort parent nodes by reference count.
   *
   * @param array $count
   *   An array of parent node IDs and reference counts keyed by ID.
   *
   * @return array
   *   An array containing the highest, lowest and middle node.
   */
  private function sortParents(array $count) {
    arsort($count);
    $ids = array_keys($count);
    $high = $ids[0];
    $mid = $ids[round((count($ids) / 2))];
    $low = array_pop($ids);
    return [$high, $mid, $low];
  }

}

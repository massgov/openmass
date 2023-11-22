<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Related Nodes computed field.
 *
 * This takes settings for fields and types of nodes that should be checked for
 * references to the current node.
 *
 * To utilize this field, implement hook_entity_bundle_field_info and add a
 * field to the $fields array. Use the following settings to setup the scope for
 * this to query:
 *   - fields
 *     This is a caveat for when it is desired to set related to values via
 *     direct references set against a field on the currently viewed entity.
 *     Example:
 *       ->setSetting('fields', [
 *         'field_event_ref_parents',
 *       ])
 *   - linkFields
 *     This should be a link field, but can be a referenced structure to the
 *     link field that includes a paragraph field with '.entity.'.
 *     Example:
 *       ->setSetting('linkFields', [
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
class RelatedNodes extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $nids_fields = $this->fields();
    $nids_links = $this->links();
    $nids_total = array_merge($nids_fields, $nids_links);
    $nids = array_slice(array_filter(array_unique($nids_total)), 0, 25);
    $i = 0;
    foreach ($nids as $nid) {
      $this->list[] = $this->createItem($i, ['target_id' => $nid]);
      $i++;
    }
  }

  /**
   * Lists all the referencing content from a field.
   *
   * @return array
   *   A list of referenced nids.
   */
  public function fields() {
    $nids = [];
    $fields = $this->getSetting('fields');
    if (!empty($fields)) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        foreach ($fields as $field) {
          if ($entity->hasField($field)) {
            /** @var \Drupal\node\Entity\Node $node */
            $nodes = $entity->get($field)->referencedEntities();
            foreach ($nodes as $node) {
              // If the node referenced by an event is an org_page, prevent
              // adding it as a reference on the event's page.
              if ($entity->bundle() === 'event' && $node->getType() === 'org_page') {
                continue;
              }
              else {
                $nids[] = $node->id();
              }
            }
          }
        }
      }
    }
    return $nids;
  }

  /**
   * Get nids that are referenced from the configured Link fields.
   *
   * @return array
   *   A list if nids.
   */
  protected function links() {
    $nids = [];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $uri = 'entity:node/' . $entity->id();

    $linkFields = $this->getSetting('linkFields');
    if (!empty($linkFields)) {
      foreach ($linkFields as $linkField) {
        $query = $this->getQuery();
        $query->condition($linkField . '.uri', $uri);
        $return = $query->accessCheck(FALSE)->execute();
        // Discard the keys in $return as we don't care about revision ids.
        $nids = array_merge($nids, array_values($return));
      }
    }

    $referenceFields = $this->getSetting('referenceFields');
    if (!empty($referenceFields)) {
      foreach ($referenceFields as $referenceField) {
        $query = $this->getQuery();
        $query->condition($referenceField . '.target_id', $entity->id());
        $return = $query->accessCheck(FALSE)->execute();
        // Discard the keys in $return as we don't care about revision ids.
        $nids = array_merge($nids, array_values($return));
      }
    }

    return $nids;
  }

  /**
   * Get an entity query object with common condition/range applied.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A query object.
   */
  protected function getQuery(): QueryInterface {
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->range(0, 25);
    return $query;
  }

}

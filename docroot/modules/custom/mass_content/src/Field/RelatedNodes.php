<?php

namespace Drupal\mass_content\Field;

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
class RelatedNodes extends QueryGeneratedEntityReferenceListUpdated {

  protected $length = 25;

  /**
   * Array of nids already used on this related to field to filter results.
   *
   * @var array
   */
  protected $nids = [];

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    // Lists all the referencing content from a field prior to building and
    // running the query to find dynamic links.
    $fields = $this->getSetting('fields');
    if (!empty($fields)) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        $i = 0;

        foreach ($fields as $field) {
          if ($entity->hasField($field)) {
            $nodes = $entity->get($field)->referencedEntities();

            /** @var \Drupal\node\Entity\Node $node */
            foreach ($nodes as $node) {
              // If the node referenced by an event is an org_page, prevent
              // adding it as a reference on the event's page.
              if ($entity->bundle() === 'event' && $node->getType() === 'org_page') {
                continue;
              }
              else {
                $this->nids[] = $node->id();
                $this->list[] = $this->createItem($i, ['target_id' => $node->id()]);
                $i++;
              }
            }
          }
        }
      }
    }
    // Continues loading related content once the event caveat is handled.
    parent::computeValue();
  }

  /**
   * {@inheritdoc}
   */
  protected function query() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $uri = 'entity:node/' . $entity->id();
    $query = \Drupal::entityQuery('node');
    $group = $query->orConditionGroup();

    $linkFields = $this->getSetting('linkFields');
    if (!empty($linkFields)) {
      foreach ($linkFields as $linkField) {
        $group->condition($linkField . '.uri', $uri);
      }
    }

    $referenceFields = $this->getSetting('referenceFields');
    if (!empty($referenceFields)) {
      foreach ($referenceFields as $referenceField) {
        $group->condition($referenceField . '.target_id', $entity->id());
      }
    }

    $types = $this->getSetting('types');
    if (!empty($types)) {
      $query->condition('type', $types, 'IN');
    }
    $query->condition('status', 1)
      ->condition($group);

    // Filter out any related odes that were manually set against this content.
    if (!empty($this->nids)) {
      $query->condition('nid', $this->nids, 'NOT IN');
    }

    return $query;
  }

}

<?php

namespace Drupal\mass_content\Field;

/**
 * Provide a list of content based on a label.
 *
 * This gets contact information or person nodes that have a matching term
 * reference.
 */
class DynamicDirectoryByLabel extends QueryGeneratedEntityReferenceListUpdated {

  /**
   * {@inheritdoc}
   */
  protected $length = 1500;

  /**
   * {@inheritdoc}
   */
  protected function query() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $term_ids = [];
    foreach ($entity->field_listdynamic_label->referencedEntities() as $term) {
      $term_ids[] = $term->id();
    }
    $query = FALSE;
    if (!empty($term_ids)) {
      $query = \Drupal::entityQuery('node')
        ->condition('field_reusable_label.entity.tid', $term_ids, 'IN')
        ->condition('type', ['contact_information', 'person'], 'IN')
        ->condition('status', 1);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    parent::computeValue();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    if (!$entity->isNew() && count($this->list) > 1) {
      // Sort the entities based on the sort field value.
      $sort = $entity->field_listdynamic_sort->value;
      /** @var \Drupal\mass_content\EntitySorter $entitySorter */
      $entitySorter = \Drupal::service('mass_content.entity_sorter');
      $entitySorter->sortEntities($this->list, $sort);
    }
  }

}

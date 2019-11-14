<?php

namespace Drupal\mass_content\Field;

/**
 * Provide a list of content based on a label.
 *
 * This gets nodes of specific types that have a matching term reference.
 */
class DynamicListByLabel extends QueryGeneratedDynamicEntityReferenceList {

  /**
   * {@inheritdoc}
   */
  protected function queries() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $term_ids = [];
    foreach ($entity->field_listdynamic_label->referencedEntities() as $term) {
      $term_ids[] = $term->id();
    }
    $types = [
      'advisory',
      'binder',
      'decision',
      'executive_order',
      'info_details',
      'regulation',
      'rules',
    ];
    $queries = [];
    $queries['node'] = \Drupal::entityQuery('node')
      ->condition('field_reusable_label.entity.tid', $term_ids, 'IN')
      ->condition('type', $types, 'IN')
      ->condition('status', 1);

    $queries['media'] = \Drupal::entityQuery('media')
      ->condition('field_document_label.entity.tid', $term_ids, 'IN')
      ->condition('status', 1);

    return $queries;
  }

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    parent::computeValue();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    $sort = $entity->field_listdynamic_sort->value;

    /** @var \Drupal\mass_content\EntitySorter $entitySorter */
    $entitySorter = \Drupal::service('mass_content.entity_sorter');

    $entitySorter->sortEntities($this->list, $sort);
  }

}

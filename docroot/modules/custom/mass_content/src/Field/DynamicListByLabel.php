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

    if (empty($term_ids)) {
      return [];
    }

    $types = [
      'advisory',
      'binder',
      'campaign_landing',
      'curated_list',
      'decision',
      'decision_tree',
      'event',
      'executive_order',
      'form_page',
      'guide_page',
      'how_to_page',
      'info_details',
      'location',
      'location_details',
      'news',
      'org_page',
      'person',
      'regulation',
      'rules',
      'service_page',
      'service_details',
      'topic_page',
    ];

    // At least one sort is required to avoid results moving around.
    // @see https://stackoverflow.com/a/23782691/1038565
    $queries = [];
    $queries['node'] = \Drupal::entityQuery('node')
      ->condition('field_reusable_label.entity.tid', $term_ids, 'IN')
      ->condition('type', $types, 'IN')
      ->condition('status', 1)
      ->sort('nid', 'ASC');

    $queries['media'] = \Drupal::entityQuery('media')
      ->condition('field_document_label.entity.tid', $term_ids, 'IN')
      ->condition('status', 1)
      ->sort('vid', 'ASC');

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

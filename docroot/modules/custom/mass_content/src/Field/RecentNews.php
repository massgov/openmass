<?php

namespace Drupal\mass_content\Field;

/**
 * Recent news field for organizations.
 */
class RecentNews extends QueryGeneratedEntityReferenceList {

  protected $length = 6;

  /**
   * {@inheritdoc}
   */
  protected function query() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'news');
    $query->condition('field_news_signees.entity.field_state_org_ref_org.entity.nid', $entity->id());
    $query->condition('status', 1);
    $query->sort('field_news_date', 'DESC');

    // Exclude any featured items.
    if ($entity->hasField('field_org_featured_news_items')) {
      $field = $entity->get('field_org_featured_news_items');
      $exclude = array_column($field->getValue(), 'target_id');
      if ($exclude) {
        $query->condition('nid', $exclude, 'NOT IN');
      }
    }

    return $query;
  }

}

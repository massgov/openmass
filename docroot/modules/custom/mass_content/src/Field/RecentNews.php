<?php

namespace Drupal\mass_content\Field;

use Drupal\mayflower\Helper;

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
    if ($entity->getEntityTypeId() === 'paragraph') {
      $node = Helper::getParentNode($entity);
    }
    else {
      $node = $entity;
    }

    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'news');
    $query->condition('field_news_signees.entity.field_state_org_ref_org.entity.nid', $node->id());
    $query->condition('field_news_type', 'blog_post', '<>');
    $query->condition('langcode', 'en');
    $query->condition('status', 1);
    $query->sort('field_date_published', 'DESC');

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

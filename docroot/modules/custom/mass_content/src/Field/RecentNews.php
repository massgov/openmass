<?php

namespace Drupal\mass_content\Field;

use Drupal\mayflower\Helper;

/**
 * Recent news field for organization and service pages.
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
    if ($node->bundle() === 'service_page') {
      $query->condition('field_related_service.target_id', $node->id());
    }
    else {
      $query->condition('field_news_signees.entity.field_state_org_ref_org.entity.nid', $node->id());
    }
    $query->condition('field_news_type', 'blog_post', '<>');
    $query->condition('langcode', 'en');
    $query->condition('status', 1);
    $query->sort('field_date_published', 'DESC');

    // Exclude any featured items.
    foreach (['field_org_featured_news_items', 'field_service_featured_news_items'] as $featured_field) {
      if (!$entity->hasField($featured_field)) {
        continue;
      }

      $field = $entity->get($featured_field);
      $exclude = array_column($field->getValue(), 'target_id');
      if ($exclude) {
        $query->condition('nid', $exclude, 'NOT IN');
      }
    }

    return $query;
  }

}

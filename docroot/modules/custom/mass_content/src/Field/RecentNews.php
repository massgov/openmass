<?php

namespace Drupal\mass_content\Field;

use Drupal\mayflower\Helper;
use Drupal\node\NodeInterface;

/**
 * Recent news field for organization and service pages.
 */
class RecentNews extends QueryGeneratedEntityReferenceList {

  protected $length = 6;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();

    // Layout Paragraphs previews use unsaved paragraph entities. Allow
    // computed news results for those previews when the parent node exists.
    if ($entity->isNew() && $entity->getEntityTypeId() !== 'paragraph') {
      return;
    }

    $query = $this->query();
    if ($query) {
      $query->range($this->start, $this->length);
      $delta = 0;
      foreach ($query->accessCheck(FALSE)->execute() as $nid) {
        $this->list[$delta] = $this->createItem($delta, ['target_id' => $nid]);
        $delta++;
      }
    }
  }

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

    if (!$node instanceof NodeInterface || $node->isNew()) {
      return NULL;
    }

    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'news');
    if ($node->bundle() === 'service_page') {
      $query->condition('field_related_service.target_id', $node->id());
    }
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

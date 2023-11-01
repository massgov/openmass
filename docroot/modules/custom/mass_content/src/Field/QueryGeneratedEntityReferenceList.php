<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Build a computed entity reference field based on a query.
 *
 * This can be used to make the results of any query a property on an entity.
 *
 * This file is needed to prevent duplication of Recent News items
 * from appearing on the Organization pages.
 */
abstract class QueryGeneratedEntityReferenceList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * The offset.
   *
   * @var int
   */
  private $start = 0;

  /**
   * The number of results.
   *
   * This is set to a very small number by default because computed properties
   * can appear in API responses, and we don't want to be adding a lot of extra
   * weight to those API responses. If you need more items, you can override
   * this in your implementing class, but you should prefer using the range()
   * method in the code that uses the field instead.
   *
   * @var int
   */
  protected $length = 3;

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    // Workaround for parent::referencedEntities() not triggering computation.
    if ($this->isEmpty()) {
      return [];
    }
    return parent::referencedEntities();
  }

  /**
   * Limit the results to a given range.
   *
   * @param int $start
   *   The offset from the start of the result set.
   * @param int $length
   *   The number of results to return.
   *
   * @return \Drupal\mass_content\Field\QueryGeneratedEntityReferenceList
   *   The ranged field.
   */
  public function range($start = 0, $length = 10) {
    $clone = clone $this;
    $clone->start = $start;
    $clone->length = $length;
    $clone->valueComputed = FALSE;
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $query = $this->query();
      if ($query) {
        $query->range($this->start, $this->length);
        $i = 0;
        foreach ($query->accessCheck(FALSE)->execute() as $nid) {
          $this->list[$i] = $this->createItem($i, ['target_id' => $nid]);
          $i++;
        }
      }
    }
  }

  /**
   * Build the query that will be run.
   *
   * This query should not limit or paginate the result set directly.  The range
   * will be set during the computeValue function.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query to run.
   */
  abstract protected function query();

}

<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList;

/**
 * Build a computed entity reference field based on a query.
 *
 * This can be used to make the results of any query a property on an entity.
 */
abstract class QueryGeneratedDynamicEntityReferenceList extends DynamicEntityReferenceFieldItemList {
  use ComputedItemListTrait;

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
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $queries = $this->queries();
      $i = 0;
      foreach ($queries as $type => $query) {
        foreach ($query->accessCheck(FALSE)->execute() as $id) {
          $this->list[$i] = $this->createItem($i, ['target_id' => $id, 'target_type' => $type]);
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
   * @return array
   *   An array of queries to run and combine keyed by entity type.
   */
  abstract protected function queries();

}

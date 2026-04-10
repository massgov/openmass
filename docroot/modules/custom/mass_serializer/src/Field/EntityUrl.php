<?php

namespace Drupal\mass_serializer\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;

/**
 * Computed `entity_url` property added for the benefit of JSONAPI.
 */
class EntityUrl extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getValue($include_computed = FALSE) {
    $this->initList();

    return parent::getValue($include_computed);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getEntity()
      ->access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \ArrayIterator {
    $this->initList();

    return parent::getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->initList();

    return parent::get($index);
  }

  /**
   * Initialize the internal field list with the modified items.
   */
  protected function initList() {
    if ($this->list) {
      return;
    }
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $this->list = [
        $this->createItem(0, $this->buildEntityUrl()),
      ];
    }
  }

  /**
   * Builds the entity URL, avoiding alias processing in unsafe Fiber contexts.
   */
  protected function buildEntityUrl(): string {
    $entity = $this->getEntity();

    // Alias lookups may suspend the current Fiber in Drupal 10.3+.
    // During entity serialization for deferred cache writes, that can be
    // illegal. Fall back to the canonical internal path in those contexts.
    if (\Fiber::getCurrent() !== NULL) {
      return $entity->toUrl('canonical', ['path_processing' => FALSE])->toString();
    }

    try {
      return $entity->toUrl()->toString();
    }
    catch (\FiberError) {
      return $entity->toUrl('canonical', ['path_processing' => FALSE])->toString();
    }
  }

}

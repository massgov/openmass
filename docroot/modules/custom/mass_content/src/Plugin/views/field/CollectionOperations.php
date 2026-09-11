<?php

namespace Drupal\mass_content\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityOperations;
use Drupal\views\ResultRow;

/**
 * Renders only the permitted Edit operation for a collection.
 */
#[ViewsField('collection_operations')]
class CollectionOperations extends EntityOperations {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = parent::render($values);

    $build['#links'] = array_intersect_key($build['#links'], ['edit' => TRUE]);

    return $build;
  }

}

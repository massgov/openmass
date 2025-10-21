<?php

namespace Drupal\mass_validation\Information;

use Drupal\entity_hierarchy\Information\ChildEntityWarning;

/**
 * Defines a value object for a child entity warning.
 *
 * @see entity_hierarchy_form_alter()
 */
class MassChildEntityWarning extends ChildEntityWarning {

  /**
   * Gets render array for child entity list.
   *
   * @return array
   *   Render array.
   */
  public function getList() {
    $child_labels = [];
    $build = ['#theme' => 'item_list'];
    foreach ($this->records as $record) {
      $child_labels[] = $record->getEntity()->toLink()->toString()->__toString();
    }
    $build['#items'] = array_map("unserialize", array_unique(array_map("serialize", $child_labels)));
    $this->cache->applyTo($build);
    return $build;
  }

}

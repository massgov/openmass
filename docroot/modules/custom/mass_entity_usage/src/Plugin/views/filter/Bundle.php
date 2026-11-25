<?php

namespace Drupal\mass_entity_usage\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\Bundle as CoreBundle;

/**
 * Safer bundle filter for related entity tables.
 */
#[ViewsFilter("mass_entity_usage_bundle")]
class Bundle extends CoreBundle {

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    if (!empty($this->options['relationship']) && $this->options['relationship'] !== 'none') {
      return parent::getEntityType();
    }

    $views_data = $this->getViewsData()->get($this->table);
    if (isset($views_data['table']['entity type'])) {
      return $views_data['table']['entity type'];
    }

    return parent::getEntityType();
  }

}

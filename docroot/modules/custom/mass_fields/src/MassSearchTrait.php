<?php

namespace Drupal\mass_fields;

use Drupal\Core\Field\FieldItemListInterface;

trait MassSearchTrait {

  /**
   * Get search value.
   */
  public function getSearch(): FieldItemListInterface {
    return $this->get('search');
  }

  /**
   * Get search snippet value.
   */
  public function getSearchNoSnippet(): FieldItemListInterface {
    return $this->get('search_nosnippet');
  }

}

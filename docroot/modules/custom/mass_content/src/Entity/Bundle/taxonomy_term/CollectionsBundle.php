<?php

namespace Drupal\mass_content\Entity\Bundle\taxonomy_term;

use Drupal\taxonomy\Entity\Term;

/**
 * A bundle class for taxonomy_term entities.
 */
class CollectionsBundle extends Term {

  public function showOnlyFutureEvents(): bool {
    if (!$this->get('field_show_only_future_events')->isEmpty()) {
      return $this->field_show_only_future_events->value;
    }
    return FALSE;
  }



}

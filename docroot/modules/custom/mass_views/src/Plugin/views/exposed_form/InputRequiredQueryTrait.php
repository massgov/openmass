<?php

namespace Drupal\mass_views\Plugin\views\exposed_form;

/**
 * Keeps the view query compiled while the exposed form still wants input.
 *
 * The "input required" exposed forms skip the whole query build as soon as they
 * decide not to show results, which leaves the view carrying an empty string
 * where its query object belongs. That costs the page nothing, but anything
 * that runs the view on its own afterwards fails on that string: Views Bulk
 * Operations resolving the rows an editor ticked, Views Bulk Edit collecting
 * the bundles of a selection. Compile the query and skip only the part that
 * costs anything - running it.
 */
trait InputRequiredQueryTrait {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->exposedFilterApplied()) {
      parent::query();
      return;
    }

    // Everything the parent does apart from marking the view as built, which
    // is the flag that stops the query from being compiled.
    $this->view->executed = TRUE;
    $this->view->result = [];
  }

}

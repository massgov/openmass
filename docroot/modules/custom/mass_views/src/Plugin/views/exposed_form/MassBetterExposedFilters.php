<?php

namespace Drupal\mass_views\Plugin\views\exposed_form;

use Drupal\better_exposed_filters\Plugin\views\exposed_form\BetterExposedFilters;

/**
 * Better Exposed Filters, with a query left ready to run.
 *
 * The module extends the core "input required" exposed form and inherits its
 * handling of the "Require input" option along with it.
 *
 * @see \Drupal\mass_views\Plugin\views\exposed_form\InputRequiredQueryTrait
 */
class MassBetterExposedFilters extends BetterExposedFilters {

  use InputRequiredQueryTrait;

}

<?php

namespace Drupal\mass_views\Plugin\views\exposed_form;

use Drupal\views\Plugin\views\exposed_form\InputRequired;

/**
 * The core "input required" exposed form, with a query left ready to run.
 *
 * @see \Drupal\mass_views\Plugin\views\exposed_form\InputRequiredQueryTrait
 */
class MassInputRequired extends InputRequired {

  use InputRequiredQueryTrait;

}

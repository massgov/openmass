<?php

namespace Drupal\mass_views\Plugin\views\exposed_form;

use Drupal\views\Plugin\views\exposed_form\InputRequired;

/**
 * Provides an exposed form with required input from the request.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "input_required_on_request",
 *   title = @Translation("Input required from the request"),
 *   help = @Translation("An exposed form that only renders a view if request contains parameters.")
 * )
 */
class InputRequiredFromRequest extends InputRequired {

  protected function exposedFilterApplied() {

    $exposed_input = \Drupal::request()->query->all();
    unset($exposed_input['_wrapper_format']);

    if (!$exposed_input) {
      return false;
    }

    return parent::exposedFilterApplied();
  }

}

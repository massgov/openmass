<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a conditionally required link group title constraint.
 *
 * @Constraint(
 *   id = "ConditionallyRequireLinkGroupTitle",
 *   label = @Translation("Conditionally require link group title", context = "Validation"),
 * )
 */
class ConditionallyRequireLinkGroupTitleConstraint extends Constraint {

  public $errorMessage = 'Required when the display type is "Links" but optional when it is "Buttons".';

}

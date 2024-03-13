<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures field_image is filled when field_background_type is 'image'.
 *
 * @Constraint(
 *   id = "BackgroundImageRequired",
 *   label = @Translation("Background Image Required", context = "Validation"),
 * )
 */
class BackgroundImageRequiredConstraint extends Constraint {

  public $message = 'Background Image is required.';

}

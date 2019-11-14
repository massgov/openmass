<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Allowed domain values.
 *
 * @Constraint(
 *   id = "AllowedIframeUrl",
 *   label = @Translation("Allowed Iframe URL", context = "Validation"),
 * )
 */
class AllowedIframeUrlConstraint extends Constraint {

  public $message = 'URL must start with an allowed URL.';

}

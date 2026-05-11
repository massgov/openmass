<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks for unsupported or invalid image sources in rich text.
 *
 * @Constraint(
 *   id = "InvalidImageSourceConstraint",
 *   label = @Translation("Invalid image source in rich text", context = "Validation"),
 * )
 */
class InvalidImageSourceConstraint extends Constraint {

  /**
   * Message shown when an invalid image source is detected.
   *
   * @var string
   */
  public $message = 'One or more images in this field use an unsupported format. Please remove those images or re-add them using the Insert Image button.';

}

<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * @Constraint(
 *   id = "UnpublishParent",
 *   label = @Translation("Unpublish parent without children", context = "Validation")
 * )
 */
class UnpublishParentConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'This content cannot be unpublished because it is a parent.';

}

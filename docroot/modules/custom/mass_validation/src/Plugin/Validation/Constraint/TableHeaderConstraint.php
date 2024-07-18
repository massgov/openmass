<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the table in the CKEditor content has headers.
 *
 * @Constraint(
 *   id = "TableHeaderConstraint",
 *   label = @Translation("Table Header Constraint", context = "Validation"),
 * )
 */
class TableHeaderConstraint extends Constraint {
  public $message = 'Tables must have headers.';

}

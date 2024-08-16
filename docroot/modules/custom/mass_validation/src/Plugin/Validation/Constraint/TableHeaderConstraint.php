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
  public $message = 'Tables must include a header row. See our <a target="_blank" href="%link">%title</a>.';
}

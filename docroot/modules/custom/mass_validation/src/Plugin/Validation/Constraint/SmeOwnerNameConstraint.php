<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Requires an SME / content owner name to end with something in parentheses.
 *
 * @Constraint(
 *   id = "MassSmeOwnerName",
 *   label = @Translation("SME / content owner name format", context = "Validation")
 * )
 */
class SmeOwnerNameConstraint extends Constraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public $message = '%name needs an organization abbreviation in parentheses at the end, for example John Doe (DPH).';

}

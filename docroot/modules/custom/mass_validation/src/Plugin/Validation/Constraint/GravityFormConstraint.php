<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains GravityFormConstraint class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Gravity Form allowed values.
 *
 * @Constraint(
 *   id = "GravityForm",
 *   label = @Translation("Gravity Form constraint", context = "Validation"),
 * )
 */
class GravityFormConstraint extends Constraint {

  /**
   * Allowed values for Gravity Form.
   *
   * @var array
   */
  public $allowedValues;

  /**
   * Message shown when Gravity Form link value is incorrect.
   *
   * @var string
   */
  public $message = '"%gravity_form_link" is an invalid link value.';

  /**
   * GravityFormConstraint constructor.
   *
   * @param mixed $options
   *   Options to operate with.
   */
  public function __construct($options = NULL) {
    if (NULL !== $options && !is_array($options)) {
      $options = [
        'gravity_form_link' => $options,
      ];
    }

    parent::__construct($options);
    if (NULL === $this->allowedValues) {
      throw new MissingOptionsException(sprintf('Gravity Form Link must be given for constraint %s', __CLASS__), ['gravity_form_link']);
    }
  }

}

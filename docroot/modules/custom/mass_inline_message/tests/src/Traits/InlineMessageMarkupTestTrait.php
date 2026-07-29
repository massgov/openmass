<?php

namespace Drupal\Tests\mass_inline_message\Traits;

use Drupal\mass_inline_message\Plugin\Validation\Constraint\InlineMessageConstraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Shared markup samples and constraint validation for Message box tests.
 */
trait InlineMessageMarkupTestTrait {

  /**
   * Overview field markup with a CKEditor div-wrapped message body.
   */
  protected const OVERVIEW_WITH_MESSAGE_BOX = '<p>Overview intro.</p>'
    . '<mass-inline-message data-title="Payment options" data-type="info">'
    . '<div><p>Visit <a href="https://www.mass.gov/pay">mass.gov/pay</a>.</p></div>'
    . '</mass-inline-message>';

  /**
   * Validates stored or authored markup against InlineMessageConstraint.
   */
  protected function validateInlineMessageMarkup(string $markup): ConstraintViolationListInterface {
    $validator = \Drupal::service('validation.basic_recursive_validator_factory')->createValidator();
    return $validator->validate($markup, new InlineMessageConstraint());
  }

}

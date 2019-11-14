<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains SocialLinkConstraintValidator class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SocialLink constraint.
 */
class SocialLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($field, Constraint $constraint) {
    /** @var \Drupal\mass_validation\Plugin\Validation\Constraint\SocialLinkConstraint $constraint */
    if (!$field->isEmpty()) {
      $allowed_values = $constraint->allowedValues;

      $invalid_link = '';
      foreach ($field->getValue() as $item) {
        $match_flag = FALSE;
        foreach ($allowed_values as $allowed_value) {
          // We take for valid url the one that have 'allowed_value' followed by
          // '/' character with the allowed values. We accept path-less entries.
          if (preg_match('/' . $allowed_value . '\//', $item['uri']) === 1) {
            $match_flag = TRUE;
            continue;
          }
        }
        if (!$match_flag) {
          $invalid_link = $item;
          break;
        }
      }

      // If we have an invalid link then add violation.
      if (is_array($invalid_link)) {
        $this->context->addViolation($constraint->message, ['%social_link_url' => $invalid_link['uri'], '%social_link_title' => $invalid_link['title']]);
      }
    }
  }

}

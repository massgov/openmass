<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains GravityFormConstraintValidator class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the GravityForm constraint.
 */
class GravityFormConstraintValidator extends ConstraintValidator {

  /**
   * Checks if a given URL is allowed based on a list of allowed domain patterns.
   *
   * @param string $url
   *   The URL to be checked.
   * @param array $allowed_patterns
   *   An array of allowed domains regexp patterns.
   *
   * @return bool
   *   Returns TRUE if the URL's hostname matches any of the allowed domains or patterns, otherwise FALSE.
   */
  private function isAllowedUrl(string $url, array $allowed_patterns): bool {
    $parsed_url = @parse_url($url);
    if (!$parsed_url || !isset($parsed_url['host'])) {
      return FALSE;
    }

    $host = $parsed_url['host'];

    foreach ($allowed_patterns as $pattern) {
      if (preg_match($pattern, $host)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($field, Constraint $constraint) {
    /** @var \Drupal\mass_validation\Plugin\Validation\Constraint\SocialLinkConstraint $constraint */
    if (!$field->isEmpty()) {
      $allowed_values = $constraint->allowedValues;
      $invalid_link = [];

      foreach ($field->getValue() as $item) {
        if ($this->isAllowedUrl($item['uri'], $allowed_values) === FALSE) {
          $invalid_link = $item;
        }
      }

      // If we have an invalid link then add violation.
      if (!empty($invalid_link)) {
        $this->context->addViolation($constraint->message, ['%gravity_form_link' => $invalid_link['uri']]);
      }
    }
  }

}

<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Iframe allowed URLs.
 */
class AllowedIframeUrlConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$value->isEmpty()) {
      // List of allowed URL patterns for iframe src url field.
      $config = \Drupal::config('mass_utility.settings');
      $urls = $config->get('allowed_urls');

      // Convert the allowed urls into a single regex.
      $patterns = array_map(function ($url) {
        return preg_quote(trim($url), '/');
      }, preg_split('/\n+/', trim($urls)));
      $pattern = '^(' . implode('|', $patterns) . ').*';

      /** @var \Drupal\link\LinkItemInterface $_value */
      foreach ($value as $_value) {
        $parts = $_value->getValue();
        if (!preg_match("/$pattern/", $parts['uri'])) {
          $this->context->buildViolation($constraint->message)
            ->setParameter('%value', $parts['uri'])
            ->addViolation();
        }
      }
    }
  }

}

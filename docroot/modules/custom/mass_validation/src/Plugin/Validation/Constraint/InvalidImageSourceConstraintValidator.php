<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates InvalidImageSourceConstraint.
 */
class InvalidImageSourceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value === NULL || $value === '') {
      return;
    }
    if (!is_string($value)) {
      return;
    }
    $value = trim($value);
    if ($value === '') {
      return;
    }

    // Parse the HTML so we can inspect <img> elements.
    $document = Html::load($value);
    $xpath = new \DOMXPath($document);
    $images = $xpath->query('//img[@src]');

    if ($images->length === 0) {
      return;
    }

    foreach ($images as $image) {
      /** @var \DOMElement $image */
      $src = trim($image->getAttribute('src'));

      if ($src === '') {
        continue;
      }

      // 1. Block any explicit data: URIs. These are not supported in our
      // text formats and get stripped by Drupal's HTML filters anyway, which
      // results in broken images on the front end.
      if (preg_match('#^data:#i', $src)) {
        $this->context->addViolation($constraint->message);
        return;
      }

      // 2. Ignore clearly normal URLs/paths (absolute, protocol-relative,
      // root-relative, or common Drupal files paths). These are allowed.
      if (preg_match('#^(https?://|//|/|sites/)#i', $src)) {
        continue;
      }

      // 3. Detect mangled base64-like sources (data: prefix stripped by filter).
      // Block any src that looks like "image/png;base64,..." with no length
      // minimum so we catch values that were already filtered before validation.
      if (preg_match('#^[a-z]+/[a-z0-9.+-]+;base64,#i', $src)) {
        $this->context->addViolation($constraint->message);
        return;
      }

      // 4. As a safety net, treat extremely long, non-whitelisted src values
      // as invalid. Legitimate image URLs are rarely this long.
      if (strlen($src) >= 2000) {
        $this->context->addViolation($constraint->message);
        return;
      }
    }
  }

}


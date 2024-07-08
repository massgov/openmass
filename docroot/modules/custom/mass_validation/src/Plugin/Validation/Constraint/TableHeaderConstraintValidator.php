<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TableHeaderConstraintValidator extends ConstraintValidator {
  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value !== null) {
      if ($text = $value->value) {
        $document = Html::load($text);
        $xpath = new \DOMXPath($document);

        // XPath query to find tables without <th> or <thead> elements.
        $xpathQuery = "//table[not(.//th) and not(.//thead)]";
        $tablesWithoutHeaders = $xpath->query($xpathQuery);

        if ($tablesWithoutHeaders->length > 0) {
          $this->context->addViolation($constraint->message);
        }
      }
    }
  }
}

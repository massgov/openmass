<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator.
 *
 * Validate if HTML table has a header.
 */
class TableHeaderConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (empty($value)) {
      return;
    }

    $document = Html::load($value);
    $xpath = new \DOMXPath($document);

    // XPath query to find tables without <th> or <thead> elements.
    $xpathQuery = "//table[not(.//th) and not(.//thead)]";
    $tablesWithoutHeaders = $xpath->query($xpathQuery);

    if ($tablesWithoutHeaders->length > 0) {
      $this->context->addViolation($constraint->message, [
        '%title' => 'Knowledge Base article on header rows',
        '%url' => '/kb/documents-images-media--tables--headers-and-captions-key-tools-for-clarity-and-accessibility',
      ]);
    }
  }

}

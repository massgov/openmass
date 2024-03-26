<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class BackgroundImageRequiredConstraintValidator extends ConstraintValidator {

  public function validate($items, Constraint $constraint) {
    $field_image = $items->getValue();
    $paragraph = $items->getEntity();
    if (empty($field_image)) {
      if ($paragraph->get('field_background_type')->value == 'image') {
        $this->context->buildViolation($constraint->message)->addViolation();
      }
    }
  }

}

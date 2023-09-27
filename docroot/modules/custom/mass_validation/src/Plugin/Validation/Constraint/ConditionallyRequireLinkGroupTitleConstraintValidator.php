<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Conditionally require link group title constraint.
 */
class ConditionallyRequireLinkGroupTitleConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if ($entity->bundle() !== 'flexible_link_group') {
      return;
    }
    $first = $entity->field_display_type->first();
    if ($first && ($first->getString() === 'links')) {
      $title = $entity->field_flexible_link_group_title->first();
      if (!$title || $title->isEmpty()) {
        $this->context->buildViolation($constraint->errorMessage)->atPath('field_flexible_link_group_title')->addViolation();
      }
    }
  }

}

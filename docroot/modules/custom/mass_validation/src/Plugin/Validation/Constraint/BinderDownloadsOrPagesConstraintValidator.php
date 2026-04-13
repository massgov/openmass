<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueInteger constraint.
 */
class BinderDownloadsOrPagesConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    /** @var \Drupal\mass_validation\Plugin\Validation\Constraint\BinderDownloadsOrPagesConstraint $constraint */
    if (!isset($entity)) {
      return;
    }

    if ($entity->hasField('field_downloads') && $entity->hasField('field_binder_pages')) {
      $field_is_populated = FALSE;
      if (!empty($entity->field_downloads->getValue())) {
        $field_is_populated = TRUE;
      }

      $page_ids = array_column($entity->get('field_binder_pages')->getValue(), 'target_id');
      $page_ids = array_filter($page_ids);
      if (!empty($page_ids)) {
        $field_is_populated = TRUE;
      }

      if (!$field_is_populated) {
        $this->context->buildViolation($constraint->message)->addViolation();
      }
    }
  }

}

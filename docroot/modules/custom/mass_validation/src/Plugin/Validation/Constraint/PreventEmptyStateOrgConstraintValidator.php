<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains PreventEmptyStateOrgConstraintValidator class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PreventEmptyStateOrg constraint.
 */
class PreventEmptyStateOrgConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    /** @var \Drupal\mass_validation\Plugin\Validation\Constraint\PreventEmptyStateOrgConstraint $constraint */
    if (!isset($entity)) {
      return;
    }
    // This field is an entity reference to paragraphs.
    if ($entity->hasField('field_news_signees')) {
      // Load the paragraphs and loop through them.
      $news_signees = $entity->get('field_news_signees')->referencedEntities();
      $state_org_found = FALSE;
      foreach ($news_signees as $signee) {
        // This is the paragraph type. At least one state organization needed.
        $type = $signee->get('type')->getValue();
        if ($type[0]['target_id'] == 'state_organization') {
          $state_org_found = TRUE;
          break;
        }
      }
      if (!$state_org_found) {
        $this->context->buildViolation($constraint->message)
          ->addViolation();
      }
    }
  }

}

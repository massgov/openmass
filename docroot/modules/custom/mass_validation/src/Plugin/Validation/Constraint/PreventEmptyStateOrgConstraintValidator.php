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
      $signee_ids = array_column($entity->get('field_news_signees')->getValue(), 'target_id');
      $signee_ids = array_values(array_filter($signee_ids));
      $state_org_found = FALSE;
      if ($signee_ids) {
        $news_signees = \Drupal::entityTypeManager()
          ->getStorage('paragraph')
          ->loadMultiple($signee_ids);
        foreach ($news_signees as $signee) {
          if ($signee->bundle() === 'state_organization') {
            $state_org_found = TRUE;
            break;
          }
        }
      }
      if (!$state_org_found) {
        $this->context->buildViolation($constraint->message)
          ->addViolation();
      }
    }
  }

}

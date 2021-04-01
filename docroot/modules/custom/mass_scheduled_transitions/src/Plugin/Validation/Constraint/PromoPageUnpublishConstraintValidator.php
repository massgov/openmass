<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityInterface;
use Drupal\mass_content_moderation\MassModeration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Promo Page Unpublish constraint.
 */
class PromoPageUnpublishConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(EntityInterface $entity, Constraint $constraint) {

    // Cannot enforce when entity is new since there is no way to create a transition yet.
    if ($entity->bundle() !== 'campaign_landing' || $entity->isNew()) {
      return;
    }

    // Add 14 months to today date.
    $today_date = new DrupalDateTime("now +14 months");
    $transitions = mass_scheduled_transitions_loadByHostEntity($entity);
    foreach ($transitions as $transition) {
      // If the unpublished date is greater than 14 months then validation error is displayed.
      if ($transition->getTransitionDate() > $today_date && $transition->getState() == MassModeration::UNPUBLISHED) {
        $this->context->buildViolation($constraint->errorMessage)
          // @DCG The path depends on entity type. It can be title, name, etc.
          ->atPath('title')
          ->addViolation();
      }
    }
  }

}

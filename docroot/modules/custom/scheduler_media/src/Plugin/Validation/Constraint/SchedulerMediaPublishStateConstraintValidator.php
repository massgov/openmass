<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint\ConstraintValidatorBase;

/**
 * Validates the SchedulerMediaPublishState constraint.
 */
class SchedulerMediaPublishStateConstraintValidator extends ConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $value->getEntity();

    // No need to validate entities that are not moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return;
    }

    // No need to validate if a moderation state has not ben set.
    if ($value->isEmpty()) {
      return;
    }

    // No need to validate when there is no time set.
    if (!isset($entity->publish_on->value)) {
      return;
    }

    $moderation_state = $entity->moderation_state->value;
    $publish_state = $entity->publish_state->value;

    if (!$this->isValidTransition($entity, $moderation_state, $publish_state)) {
      $this->context
        ->buildViolation($constraint->invalidTransitionMessage, [
          '%publish_state' => $publish_state,
          '%content_state' => $moderation_state,
        ])
        ->atPath('publish_state')
        ->addViolation();
    }
  }

}

<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Scheduled Transitions Reschedule constraint.
 */
class ScheduledTransitionsRescheduleConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $host_entity = $entity->getEntity();
    $duration = $host_entity->bundle() == 'alert' ? MASS_SCHEDULED_TRANSITIONS_ALERT_MAX_DURATION : MASS_SCHEDULED_TRANSITIONS_CAMPAIGN_LANDING_MAX_DURATION;
    $max_time = new DrupalDateTime("now +" . MASS_SCHEDULED_TRANSITIONS_ALERT_MAX_DURATION);
    if ($entity->getTransitionDate()->getTimestamp() > $max_time->getTimestamp()) {
      $this->context->buildViolation($constraint->errorMessage)
        // @DCG The path depends on entity type. It can be title, name, etc.
        // ->atPath('title')
        ->addViolation();
    }
  }
}

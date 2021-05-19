<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Scheduled Transitions Reschedule constraint.
 *
 * @Constraint(
 *   id = "MassScheduledTransitionsScheduledTransitionsReschedule",
 *   label = @Translation("Scheduled Transitions Reschedule", context = "Validation"),
 * )
 *
 * @DCG
 * To apply this constraint on a particular field implement
 * hook_entity_type_build().
 */
class ScheduledTransitionsRescheduleConstraint extends Constraint {

  public $errorMessage = 'Your transition is scheduled for too far in the future.';

}

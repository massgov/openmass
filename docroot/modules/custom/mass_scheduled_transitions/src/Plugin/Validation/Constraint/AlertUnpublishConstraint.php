<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Alert Unpublish constraint.
 *
 * @Constraint(
 *   id = "MassScheduledTransitionsAlertUnpublish",
 *   label = @Translation("Alert Unpublish", context = "Validation"),
 *   type = "entity:node"
 * )
 *
 * @DCG
 * To apply this constraint on a particular field implement
 * hook_entity_type_build().
 */
class AlertUnpublishConstraint extends Constraint {

  public $errorMessage = 'An unpublish scheduled transition within the next 14 days must be provided for any published alert.';

}

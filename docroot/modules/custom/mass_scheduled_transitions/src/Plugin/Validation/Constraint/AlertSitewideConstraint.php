<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Symfony\Component\Validator\Constraint;

/**
 * Provides a Alert Sitewide constraint.
 *
 * @Constraint(
 *   id = "MassScheduledTransitionsAlertSitewide",
 *   label = @Translation("Alert Sitewide", context = "Validation"),
 *   type = "entity:scheduled_transition"
 * )
 */
class AlertSitewideConstraint extends Constraint {
  public $errorMessage = 'You must have permission to create/edit sitewide alerts in order to modify this scheduled transition.';

}

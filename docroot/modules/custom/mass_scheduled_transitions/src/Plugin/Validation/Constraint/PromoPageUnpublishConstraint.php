<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Promo Page Unpublish constraint.
 *
 * @Constraint(
 *   id = "MassScheduledTransitionsPromoPageUnpublish",
 *   label = @Translation("Promo Page Unpublish", context = "Validation"),
 * )
 *
 * @DCG
 * To apply this constraint on a particular field implement
 * hook_entity_type_build().
 */
class PromoPageUnpublishConstraint extends Constraint {

  public $errorMessage = 'An unpublish date within the next 14 months must be provided.';

}

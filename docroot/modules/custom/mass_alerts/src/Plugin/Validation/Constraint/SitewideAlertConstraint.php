<?php

namespace Drupal\mass_alerts\Plugin\Validation\Constraint;

/**
 * @file
 * Contains SitewideAlertConstraint class.
 */

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Defines a class for sitewide alert constraints.
 *
 * @Constraint(
 *   id = "SitewideAlert",
 *   label = @Translation("Constraints for managing sitewide alerts", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class SitewideAlertConstraint extends CompositeConstraintBase {
  /**
   * Message shown when a user does not have permission to act on sitewide alerts.
   *
   * @var string
   */
  public $message = 'Only users with elevated permissions can manage sitewide alerts.';

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return [
      'field_alert_display',
      'moderation_state',
    ];
  }

}

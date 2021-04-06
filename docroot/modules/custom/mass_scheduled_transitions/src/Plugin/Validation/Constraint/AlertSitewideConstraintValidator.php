<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Alert Sitewide constraint.
 */
class AlertSitewideConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($transition, Constraint $constraint) {

    /** @var \Drupal\node\Entity\Node $entity */
    $entity = $transition->getEntity();

    if ($entity->bundle() !== 'alert' || $entity->get("field_alert_display")->getValue() !== 'site_wide') {
      return;
    }

    if (!\Drupal::currentUser()->hasPermission('create site wide alerts')) {
      $this->context->buildViolation($constraint->errorMessage)
        ->addViolation();
    }
  }
}

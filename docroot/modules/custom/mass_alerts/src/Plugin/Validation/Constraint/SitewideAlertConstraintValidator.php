<?php

namespace Drupal\mass_alerts\Plugin\Validation\Constraint;

/**
 * @file
 * Contains SitewideAlertConstraintValidator class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SitewideAlert constraint.
 */
class SitewideAlertConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $value->getEntity();

    // 1. Are we creating a new sitewide alert, or modifying an existing one?
    if ($entity->getEntityTypeId() === "node" && $entity->bundle() === "alert") {
      $alert_placement = $entity->get("field_alert_display")->getValue();
      if (!empty($alert_placement) && $alert_placement[0]['value'] === "site_wide") {
        // 2. Does the user has special sitewide alerts permission?
        if (!\Drupal::currentUser()->hasPermission('create site wide alerts')) {
          $this->context->buildViolation($constraint->message)->addViolation();
        }
      }
    }

  }

}

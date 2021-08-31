<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains PreventEditGovLinkConstraintValidator class.
 */

use Drupal\Component\Utility\Html;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PreventEditGovLink constraint.
 */
class PreventEditGovLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    /** @var \Drupal\mass_validation\Plugin\Validation\Constraint\PreventEditGovLinkConstraint $constraint */
    $covers_fields = $constraint->coversFields();
    foreach ($covers_fields as $entity_field) {
      /** @var \Drupal\Core\Entity\Entity $entity */
      if ($entity->hasField($entity_field)) {
        $values = $entity->get($entity_field)->getValue();
        // Validate link fields.
        if (isset($values[0]['uri'])) {
          foreach ($values as $index => $link) {
            if (strpos($link['uri'], 'edit.mass.gov') !== FALSE) {
              $this->context->buildViolation($constraint->message)
                ->atPath($entity_field . '.' . $index)
                ->addViolation();
            }
          }
        }
        // Other text fields validation.
        if (isset($values[0]['value'])) {
          foreach ($values as $index => $value) {
            $html = HTML::load($value['value']);
            if (!empty($html)) {
              $anchors = $html->getElementsByTagName("a");
              if (!empty($anchors)) {
                foreach ($anchors as $anchor) {
                  $href = $anchor->getAttribute('href');
                  if (strpos($href, "edit.mass.gov") !== FALSE) {
                    $this->context->buildViolation($constraint->message)
                      ->atPath($entity_field)
                      ->addViolation();
                  }
                }
              }
            }
          }
        }
      }
    }
  }

}

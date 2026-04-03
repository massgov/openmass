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
            if (!empty($link['uri']) && strpos($link['uri'], 'edit.mass.gov') !== FALSE) {
              // Attach the violation to the field as a whole rather than a
              // specific delta. This avoids issues where deeply nested or
              // AJAX-rendered widgets (such as layout_paragraphs link fields)
              // cannot be resolved to a concrete form element, which would
              // otherwise result in FormState::setError() receiving a NULL
              // element and triggering a TypeError.
              $this->context->buildViolation($constraint->message)
                ->atPath($entity_field)
                ->addViolation();
              // One violation per field is sufficient.
              break;
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

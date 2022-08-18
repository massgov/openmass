<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks that the same entity is not referenced multiple times.
 */
class DuplicateReferenceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $values = array_map(function ($item) {
      return $item['target_id'];
    }, $value->filterEmptyItems()->getValue());

    $values = array_filter($values);
    $occurrences = array_count_values($values);

    $duplicate_keys = array_keys(array_filter($values, function ($target_id) use ($occurrences) {
      return $occurrences[$target_id] > 1;
    }));

    foreach ($duplicate_keys as $key) {
      $entity = $value->get($key)->entity;
      $this->context->buildViolation($constraint->message)
        ->setParameter('%label', EntityAutocomplete::getEntityLabels([$entity]))
        ->setInvalidValue($entity)
        ->atPath((string) $key . '.target_id')
        ->addViolation();
    }
  }

}


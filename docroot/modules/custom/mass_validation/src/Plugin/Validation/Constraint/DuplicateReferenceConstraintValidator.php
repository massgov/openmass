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
    $target_type = $value->getFieldDefinition()
      ->getSetting('target_type');
    $values = array_map(function ($item) {
      return $item['target_id'];
    }, $value->filterEmptyItems()->getValue());

    $values = array_filter($values);
    $occurrences = array_count_values($values);

    $duplicate_keys = array_keys(array_filter($values, function ($target_id) use ($occurrences) {
      return $occurrences[$target_id] > 1;
    }));

    $entities = [];
    if ($target_type && $values) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($target_type)
        ->loadMultiple(array_unique($values));
    }

    foreach ($duplicate_keys as $key) {
      $target_id = $values[$key] ?? NULL;
      $entity = $target_id ? ($entities[$target_id] ?? NULL) : NULL;
      $label = $entity ? EntityAutocomplete::getEntityLabels([$entity]) : (string) $target_id;
      $this->context->buildViolation($constraint->message)
        ->setParameter('%label', $label)
        ->setInvalidValue($entity)
        ->atPath((string) $key . '.target_id')
        ->addViolation();
    }
  }

}

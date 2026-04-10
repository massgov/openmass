<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\mass_content_moderation\MassModeration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if an entity has a parent before unpublishing.
 */
class PublishChildWithUnpublishedParentConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!isset($entity)) {
      return;
    }

    // When trying to publish an entity.
    if ($entity->getModerationState()->value != MassModeration::PUBLISHED) {
      return;
    }

    if (!$parentList = $entity->getPrimaryParent()) {
      return;
    }

    $parent_id = $parentList->target_id;
    if (!$parent_id) {
      return;
    }
    $parent = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($parent_id);

    // If we can load the parent successfully.
    if (!$parent) {
      return;
    }

    $parent_state = $parent->getModerationState()->value;

    // The parent cannot be unpublished or in the trash.
    if ($parent_state == MassModeration::PUBLISHED) {
      return;
    }

    $this->context->addViolation(PublishChildWithUnpublishedParentConstraint::MESSAGE);
  }

}

<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
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
    if ($entity->moderation_state->value != MassModeration::PUBLISHED) {
      return;
    }

    // If the entity has a parent.
    if (!($entity->field_primary_parent ?? FALSE) || !($entity->field_primary_parent[0] ?? FALSE)) {
      return;
    }

    $value = $entity->field_primary_parent[0]->getValue();
    $target_id = $value['target_id'] ?? FALSE;
    $parent = $target_id ? Node::load($target_id) : FALSE;

    // If we can load the parent sucessfully.
    if (!$parent) {
      return;
    }

    $parent_state = $parent->moderation_state->value;

    // The parent cannot be unpublished or in the trash.
    if ($parent_state == MassModeration::PUBLISHED) {
      return;
    }

    $this->context->addViolation(PublishChildWithUnpublishedParentConstraint::MESSAGE);
  }

}

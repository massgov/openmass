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
    $failed = FALSE;
    if (!isset($entity)) {
      return;
    }

    // When trying to publish an entity.
    /** @var \Drupal\mass_content\Entity\Bundle\node\NodeBundle $entity */
    if ($entity->getModerationState()->getString() != MassModeration::PUBLISHED) {
      return;
    }

    $parentList = $entity->getPrimaryParent();
    if (!$parentList->isEmpty()) {
      $refs = $parentList->referencedEntities();
      $parent = $refs[0] ?? FALSE;
      if (!$parent) {
        $failed = TRUE;
      }
      elseif (!$parent->isPublished()) {
        $failed = TRUE;
      }
    }
    if ($failed) {
      $this->context->addViolation(PublishChildWithUnpublishedParentConstraint::MESSAGE);
    }
  }

}

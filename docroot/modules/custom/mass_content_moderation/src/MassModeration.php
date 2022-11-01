<?php

namespace Drupal\mass_content_moderation;

use Drupal\Core\Entity\EntityInterface;

/**
 * Some helpers to inform the IDE.
 */
class MassModeration {

  // To prevent typos and enable IDE auto-complete, use these constants instead
  // of strings.
  const DRAFT = 'draft';
  const NEEDS_REVIEW = 'needs_review';
  const PREPUBLISHED_DRAFT = 'prepublished_draft';
  const PREPUBLISHED_NEEDS_REVIEW = 'prepublished_needs_review';
  const PUBLISHED = 'published';
  const TRASH = 'trash';
  const UNPUBLISHED = 'unpublished';
  const FIELD_NAME = 'moderation_state';

  /**
   * Get an array of states that come "before" published.
   *
   * @return array
   *   All prepublish states.
   */
  public static function getPrepublishedStates() {
    return [
      self::DRAFT,
      self::NEEDS_REVIEW,
      self::PREPUBLISHED_DRAFT,
      self::PREPUBLISHED_NEEDS_REVIEW,
    ];
  }

  /**
   * Determine whether entity is moderated and is in a prepublish state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if published revision is in a prepublish moderation state.
   */
  public static function isPrepublish(EntityInterface $entity) {
    /** @var \Drupal\content_moderation\ModerationInformation $moderation_info */
    $moderation_info = \Drupal::service('content_moderation.moderation_information');
    return $moderation_info->isModeratedEntity($entity) && in_array($entity->get('moderation_state')->getString(), self::getPrepublishedStates());
  }

}

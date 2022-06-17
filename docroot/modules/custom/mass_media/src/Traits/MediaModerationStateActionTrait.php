<?php

declare(strict_types = 1);

namespace Drupal\mass_media\Traits;

use Drupal\media\MediaInterface;

/**
 * Trait containing reusable code for media moderation state actions.
 */
trait MediaModerationStateActionTrait {

  /**
   * {@inheritdoc}
   */
  public function createRevision(MediaInterface $entity = NULL, $moderation_state) {
    $entity->set('moderation_state', $moderation_state);
    $entity->setNewRevision(TRUE);
    $entity->setRevisionLogMessage('Moderation state set to ' . $moderation_state . ' by bulk action.');
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->setRevisionUserId(\Drupal::currentUser()->id());
    $entity->save();
  }

}

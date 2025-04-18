<?php

declare(strict_types=1);

namespace Drupal\mass_media\Traits;

use Drupal\media\MediaInterface;

/**
 * Trait containing reusable code for media moderation state actions.
 */
trait MediaModerationStateActionTrait {

  /**
   * {@inheritdoc}
   */
  public function createRevision(MediaInterface $entity, $moderation_state) {
    $revision_message = $this->t('Bulk action: Moderation state set to @moderation_state for media item @entity_id.', [
      '@moderation_state' => $moderation_state,
      '@entity_id' => $entity->id(),
    ]);

    $entity->set('moderation_state', $moderation_state);
    $entity->setNewRevision(TRUE);
    $entity->setRevisionLogMessage($revision_message);
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->setRevisionUserId(\Drupal::currentUser()->id());
    $entity->save();

    \Drupal::logger('mass_media')->notice($revision_message);
  }

}

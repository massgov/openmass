<?php

namespace Drupal\mass_media\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;

/**
 * Updates the moderation state of a media item to Published.
 *
 * @Action(
 *   id = "mass_media_publish",
 *   label = @Translation("Moderation: Publish media"),
 *   type = "media"
 * )
 */
class MediaModerationStatePublish extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(MediaInterface $entity = NULL) {
    if ($entity) {
      $entity->set('moderation_state', 'published');
      $entity->setNewRevision(TRUE);
      $entity->revision_log_message = 'Moderation state for ' . $entity->id() . ' changed by bulk action to Published';
      $entity->setRevisionCreationTime(REQUEST_TIME);
      $entity->setRevisionUserId(\Drupal::currentUser()->id());
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\mass_content\Entity\Bundle\media\MediaBundle $object */
    // Get current moderation state.
    $state = $object->getModerationState()->getString();

    if ($state == "restricted") {
      // Get the current user.
      $uid = \Drupal::currentUser()->id();
      $current_user_roles = \Drupal::currentUser()->getRoles();
      $author_id = $object->getOwner()->id();

      // If the user is not an administrator.
      if (!in_array("administrator", $current_user_roles)) {
        // If the current user is not the author.
        if ($uid !== $author_id) {
          return;
        }
      }
    }

    $access = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}

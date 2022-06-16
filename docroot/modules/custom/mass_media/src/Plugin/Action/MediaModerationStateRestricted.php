<?php

namespace Drupal\mass_media\Plugin\Action;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\file\Entity\File;

/**
 * Updates the moderation state of a media item to Restricted.
 *
 * @Action(
 *   id = "mass_media_restricted",
 *   label = @Translation("Moderation: Restrict media"),
 *   type = "media"
 * )
 */
class MediaModerationStateRestricted extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(MediaInterface $entity = NULL) {
    if ($entity) {
      $entity->set('moderation_state', 'restricted');
      $entity->setNewRevision(TRUE);
      $entity->revision_log_message = 'Moderation state for media entity ' . $entity->id() . ' changed by bulk action to Restricted';
      $entity->setRevisionCreationTime(REQUEST_TIME);
      $entity->setRevisionUserId(\Drupal::currentUser()->id());
      $entity->save();

      // Move file to private storage.
      $file = File::load($entity->field_upload_file->target_id);
      // Path to save files to.
      $directory = "documents" . "/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";

      file_move($file, 'private://' . $directory, FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\media\MediaInterface $object */
    $access = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('update', $account, TRUE));

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

    return $return_as_object ? $access : $access->isAllowed();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use Drupal\mass_media\Traits\MediaModerationStateActionTrait;
use Drupal\media\MediaInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Updates the moderation state of a media item to Restricted.
 */
#[Action(
  id: "mass_media_restricted",
  label: new TranslatableMarkup('Moderation: Restrict media'),
  type: 'media'
)]
class MediaModerationStateRestricted extends ViewsBulkOperationsActionBase {

  use MediaModerationStateActionTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(?MediaInterface $entity = NULL) {
    if ($entity) {
      $this->createRevision($entity, 'restricted');

      // Move file to private storage.
      $file = File::load($entity->field_upload_file->target_id);
      // Path to save files to.
      $directory = "documents" . "/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";

      \Drupal::service('file.repository')->move($file, 'private://' . $directory, FileExists::Replace);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
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

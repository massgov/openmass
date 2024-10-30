<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mass_media\Traits\MediaModerationStateActionTrait;
use Drupal\media\MediaInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Updates the moderation state of a media item to Unpublished.
 */
#[Action(
  id: "mass_media_unpublish",
  label: new TranslatableMarkup('Moderation: Unpublish media'),
  type: 'media'
)]
class MediaModerationStateUnpublish extends ViewsBulkOperationsActionBase {

  use MediaModerationStateActionTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(?MediaInterface $entity = NULL) {
    if ($entity) {
      $this->createRevision($entity, 'unpublished');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\media\MediaInterface $object */
    // Get current moderation state.
    $state = $object->get('moderation_state')->getString();

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

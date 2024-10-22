<?php

namespace Drupal\mass_content_moderation\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Restore Action to move items from Trash to Unpublished state.
 */
#[Action(
  id: "mass_content_moderation_restore_action",
  label: new TranslatableMarkup('Restore item from trash')
)]
class RestoreAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

    $previous_moderation_state = $entity->moderation_state->value;

    // Only restoring items in Trash.
    if ($previous_moderation_state != MassModeration::TRASH) {
      return;
    }
    $next_moderation_state = MassModeration::UNPUBLISHED;

    /** @var \Drupal\node\Entity\Node $entity */
    $entity->moderation_state = $next_moderation_state;
    $entity->setNewRevision(TRUE);
    $entity->setRevisionUserId(\Drupal::currentUser()->id());
    $entity->setRevisionLogMessage('Unpublished with "Restore item from trash" action.');
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->save();
    return $this->t('Restored: ') . ' ' . $entity->label() . ' - ' . $entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}

<?php

namespace Drupal\mass_fields\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * OOP hook implementations for field access.
 */
class MassFieldsHooks {

  /**
   * Restricts the fields that only some roles may see or change.
   *
   * Every field rule for this module belongs here rather than in
   * mass_fields_entity_field_access(). Core collects the hook results into one
   * array keyed by module name, so a second implementation from the same module
   * silently overwrites the first and its decision is lost.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($operation !== 'edit' && $operation !== 'view') {
      return AccessResult::neutral();
    }

    // The SME / content owner names staff and is internal tracking only, so it
    // is withheld from anyone who cannot already see the editorial listings.
    // Read access is open by default, which left the field internal only for as
    // long as nothing rendered it, and that is how it reached the public API.
    if ($operation === 'view' && $field_definition->getName() === 'field_sme_content_owner') {
      return AccessResult::forbiddenIf(!$account->hasPermission('access content overview'))
        ->cachePerPermissions();
    }

    // User approval fields are for access managers only.
    if ($field_definition->getTargetEntityTypeId() === 'user') {
      switch ($field_definition->getName()) {
        case 'field_approved':
        case 'field_approval_notes':
          return AccessResult::forbiddenIf(!$account->hasPermission('administer users'))
            ->cachePerPermissions();
      }
    }

    return AccessResult::neutral();
  }

}

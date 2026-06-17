<?php

namespace Drupal\mass_fields\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * OOP hook implementations for DP-47095 user approval field access.
 */
class MassFieldsHooks {

  /**
   * Restricts user approval fields to access managers.
   *
   * This hook class intentionally handles only the DP-47095 fields:
   * - field_approved
   * - field_approval_notes.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($operation !== 'edit' && $operation !== 'view') {
      return AccessResult::neutral();
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

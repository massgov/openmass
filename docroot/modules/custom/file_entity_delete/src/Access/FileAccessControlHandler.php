<?php

namespace Drupal\file_entity_delete\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileAccessControlHandler as BaseFileAccessControlHandler;

/**
 * Access control override for file entities.
 *
 * Allows deletion if the user has the 'delete file entities' permission.
 */
class FileAccessControlHandler extends BaseFileAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $momSays = parent::checkAccess($entity, $operation, $account);

    if ($operation === 'delete') {
      $adminAccess = AccessResult::allowedIfHasPermission($account, 'delete file entities');
      if ($adminAccess->isAllowed()) {
        return $adminAccess;
      }
    }

    return $momSays;
  }

}

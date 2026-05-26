<?php

/**
 * @file
 * Deploy hooks for mass_org_access.
 */

use Drupal\user\UserInterface;

/**
 * Populates field_default_organizations from each user's permission groups.
 */
function mass_org_access_deploy_populate_user_default_orgs(&$sandbox): ?string {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  /** @var \Drupal\mass_org_access\OrgAccessChecker $checker */
  $checker = \Drupal::service('mass_org_access.org_access_checker');
  $storage = \Drupal::entityTypeManager()->getStorage('user');
  $batch_size = 100;

  if (!isset($sandbox['total'])) {
    $count_query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 0, '>')
      ->exists('field_user_org')
      ->sort('uid');
    $sandbox['total'] = (int) $count_query->count()->execute();
    $sandbox['progress'] = 0;
    $sandbox['last_uid'] = 0;
    $sandbox['updated'] = 0;

    if ($sandbox['total'] === 0) {
      $sandbox['#finished'] = 1;
      return 'No active users with permission groups to migrate.';
    }
  }

  $uids = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('status', 1)
    ->condition('uid', $sandbox['last_uid'], '>')
    ->exists('field_user_org')
    ->sort('uid')
    ->range(0, $batch_size)
    ->execute();

  if (empty($uids)) {
    $sandbox['#finished'] = 1;
    return t('Populated default organizations for @updated of @total active users with permission groups.', [
      '@updated' => $sandbox['updated'],
      '@total' => $sandbox['total'],
    ]);
  }

  /** @var \Drupal\user\UserInterface[] $users */
  $users = $storage->loadMultiple($uids);
  foreach ($users as $user) {
    $sandbox['last_uid'] = (int) $user->id();
    $sandbox['progress']++;
    if ($user instanceof UserInterface && $checker->populateUserDefaultOrganizationsFromPermissionGroups($user)) {
      $user->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = ($sandbox['progress'] >= $sandbox['total'])
    ? 1
    : ($sandbox['progress'] / $sandbox['total']);

  if ($sandbox['#finished'] >= 1) {
    return t('Populated default organizations for @updated of @total active users with permission groups.', [
      '@updated' => $sandbox['updated'],
      '@total' => $sandbox['total'],
    ]);
  }

  return NULL;
}

<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Org Access.
 */

/**
 * Derives Permission Groups on top-level Collections from Organization(s).
 *
 * The field is new on Collections, and entity presave fills it only when a
 * Collection is saved. Without it editors cannot edit a Collection once
 * organization access is enforced.
 */
function mass_org_access_deploy_collection_permission_groups(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $tids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'collections')
    ->condition('parent', 0)
    ->execute();
  /** @var \Drupal\mass_org_access\OrgAccessChecker $checker */
  $checker = \Drupal::service('mass_org_access.org_access_checker');
  $updated = 0;
  foreach ($storage->loadMultiple($tids) as $term) {
    if ($checker->reconcileOwnerGroupsFromOrganizations($term)) {
      $term->save();
      $updated++;
    }
  }
  return sprintf('Updated Permission Groups on %d of %d top-level Collections.', $updated, count($tids));
}

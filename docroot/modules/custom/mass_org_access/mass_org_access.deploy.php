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
 * organization access is enforced. Collections that still have no Permission
 * Groups afterwards (no Organization, or Organizations without groups) are
 * named in the output and logged, because they stay admin-only.
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
  $without_groups = [];
  foreach ($storage->loadMultiple($tids) as $term) {
    if ($checker->reconcileOwnerGroupsFromOrganizations($term)) {
      $term->save();
      $updated++;
    }
    if (!$checker->getEntityOrgTids($term)) {
      $without_groups[] = sprintf('%s (%d)', $term->label(), $term->id());
    }
  }
  $message = sprintf('Updated Permission Groups on %d of %d top-level Collections.', $updated, count($tids));
  if ($without_groups) {
    $warning = sprintf('%d top-level Collections still have no Permission Groups and only administrators can edit them while organization access is enforced: %s.', count($without_groups), implode(', ', $without_groups));
    \Drupal::logger('mass_org_access')->warning('@warning', ['@warning' => $warning]);
    $message .= ' ' . $warning;
  }
  return $message;
}

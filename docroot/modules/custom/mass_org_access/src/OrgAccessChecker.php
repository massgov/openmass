<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Checks and syncs org-based access for content entities.
 */
class OrgAccessChecker {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the user's org taxonomy term IDs (empty array if none assigned).
   *
   * The field_user_org is multi-valued, so a single user may belong to
   * several organizations. Cached per request via drupal_static().
   */
  public function getUserOrgTids(AccountInterface $account): array {
    $cache = &drupal_static(__METHOD__);
    $uid = $account->id();
    if (!isset($cache[$uid])) {
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$user || !$user->hasField('field_user_org')) {
        $cache[$uid] = [];
      }
      else {
        $ids = [];
        foreach ($user->get('field_user_org') as $item) {
          if (!$item->isEmpty()) {
            $ids[] = (int) $item->target_id;
          }
        }
        $cache[$uid] = $ids;
      }
    }
    return $cache[$uid];
  }

  /**
   * Returns user_organization term IDs from field_content_organization.
   */
  public function getEntityOrgTids(EntityInterface $entity): array {
    if (!$entity->hasField('field_content_organization')) {
      return [];
    }
    return array_column($entity->get('field_content_organization')->getValue(), 'target_id');
  }

  /**
   * Returns TRUE if the account may write to the entity based on org membership.
   *
   * Neutral cases (returns TRUE, no restriction applied):
   * - User has no org assigned
   * - Entity has no org TIDs populated yet (e.g. during backfill rollout)
   */
  public function userHasOrgAccess(AccountInterface $account, EntityInterface $entity): bool {
    $user_tids = $this->getUserOrgTids($account);
    if (empty($user_tids)) {
      return TRUE;
    }
    $entity_tids = $this->getEntityOrgTids($entity);
    if (empty($entity_tids)) {
      return TRUE;
    }
    return !empty(array_intersect(
      array_map('intval', $user_tids),
      array_map('intval', $entity_tids)
    ));
  }

  /**
   * Backfill: derive field_content_organization from the entity's orgs.
   *
   * For every org_page NID in field_organizations, reverse-looks-up the
   * user_organization term(s) mapped to that org via field_state_organization,
   * adds each term plus its ancestors, and writes the union onto the entity.
   * This is the same derivation the live edit form runs (see
   * OrgLookupController), so the bulk job and the on-edit population stay in
   * lock-step — one source of truth (the State Organizations taxonomy), not
   * the org_page node, which is content and may be deleted/unpublished.
   *
   * Used by `drush moab`. Org_page bundle is intentionally skipped: its own
   * Permission Groups are curated by the content team and must not be
   * overwritten. If no related org maps to any term, the entity is left
   * untouched — it stays admin-only-editable until a mapping exists.
   *
   * @return bool
   *   TRUE if the entity's field_content_organization was updated to a
   *   new value. FALSE if nothing changed — the caller can use this to
   *   skip an unnecessary save().
   */
  public function populateOwnerGroupsFromOrganizations(EntityInterface $entity): bool {
    if (!$entity->hasField('field_content_organization')) {
      return FALSE;
    }
    if ($entity instanceof NodeInterface && $entity->bundle() === 'org_page') {
      return FALSE;
    }
    // Never overwrite an entity whose Owner Groups are already populated
    // — admins/content_team may have curated the value by hand. Backfill
    // only fills empty fields.
    if (!$entity->get('field_content_organization')->isEmpty()) {
      return FALSE;
    }
    if (!$entity->hasField('field_organizations') || $entity->get('field_organizations')->isEmpty()) {
      return FALSE;
    }
    $org_nids = array_values(array_unique(array_filter(array_map(
      fn(array $item) => (int) ($item['target_id'] ?? 0),
      $entity->get('field_organizations')->getValue()
    ))));
    if (empty($org_nids)) {
      return FALSE;
    }
    $new_tids = $this->ownerGroupTidsForOrgs($org_nids);
    if (empty($new_tids)) {
      return FALSE;
    }
    $entity->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $new_tids)
    );
    return TRUE;
  }

  /**
   * Permission Group terms mapped to a single org_page, plus ancestors.
   *
   * Reverse lookup: finds every user_organization term whose
   * field_state_organization references $org_nid, then walks each up the
   * taxonomy via loadAllParents() so ancestor (broader) groups are included.
   * The org_page node itself is not the source — only the taxonomy mapping —
   * so an unmapped or out-of-sync org silently yields an empty result.
   *
   * Single source of truth for both the live edit form (OrgLookupController)
   * and the bulk backfill.
   *
   * @return array<int, array{tid: int, label: string}>
   *   Keyed by tid; each value is {tid, label}.
   */
  public function ownerGroupTermsForOrg(int $org_nid): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'user_organization')
      ->condition('field_state_organization.target_id', $org_nid)
      ->execute();
    $collected = [];
    foreach ($tids as $tid) {
      foreach ($term_storage->loadAllParents($tid) as $parent_tid => $term) {
        if (!isset($collected[$parent_tid])) {
          $collected[$parent_tid] = [
            'tid' => (int) $parent_tid,
            'label' => $term->label(),
          ];
        }
      }
    }
    return $collected;
  }

  /**
   * Deduped, sorted Permission Group TIDs for a set of org_page NIDs.
   *
   * @param int[] $org_nids
   *   org_page node IDs.
   *
   * @return int[]
   *   Sorted unique user_organization term IDs (terms + ancestors).
   */
  public function ownerGroupTidsForOrgs(array $org_nids): array {
    $tids = [];
    foreach ($org_nids as $org_nid) {
      foreach ($this->ownerGroupTermsForOrg((int) $org_nid) as $term) {
        $tids[$term['tid']] = TRUE;
      }
    }
    $result = array_map('intval', array_keys($tids));
    sort($result);
    return $result;
  }

}

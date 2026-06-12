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
   * For every org_page NID in field_organizations, copies the Permission
   * Groups curated on that org_page's own field_content_organization and
   * writes the union onto the entity. This is the same derivation the live
   * edit form runs (see OrgLookupController), so the bulk job and the on-edit
   * population stay in lock-step.
   *
   * Used by `drush moab`. Org_page bundle is intentionally skipped: its own
   * Permission Groups are the source and must not be overwritten. If no
   * referenced org_page carries any Permission Groups, the entity is left
   * untouched — it stays admin-only-editable until the org pages are curated.
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
   * Permission Group terms taken directly from an org_page.
   *
   * Direct lookup: loads the org_page node and returns the user_organization
   * terms on its own field_content_organization — the Permission Groups the
   * content team curates by hand. Their hierarchy picker already stores any
   * ancestor terms, so the field is copied verbatim with no taxonomy walk. A
   * missing node, a non-org_page, or an empty field yields an empty result.
   *
   * Single source of truth for both the live edit form (OrgLookupController)
   * and the bulk backfill.
   *
   * @return array<int, array{tid: int, label: string}>
   *   Keyed by tid; each value is {tid, label}.
   */
  public function ownerGroupTermsForOrg(int $org_nid): array {
    $node = $this->entityTypeManager->getStorage('node')->load($org_nid);
    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'org_page'
      || !$node->hasField('field_content_organization')) {
      return [];
    }
    $collected = [];
    foreach ($node->get('field_content_organization')->referencedEntities() as $term) {
      $tid = (int) $term->id();
      $collected[$tid] ??= ['tid' => $tid, 'label' => $term->label()];
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
   *   Sorted unique user_organization term IDs copied from each org_page.
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

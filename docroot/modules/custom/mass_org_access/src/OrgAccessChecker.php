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
   * Backfill: copy field_content_organization from every related org_page.
   *
   * Iterates every NID in field_organizations, loads each org_page, and
   * writes the union of their field_content_organization terms onto the
   * entity. Org_page Owner Groups are curated manually by the content
   * team and already include ancestor terms — no walk needed.
   *
   * Used by `drush moab` only. Org_page bundle is intentionally skipped:
   * its Owner Groups are the source of truth and must not be overwritten.
   * If none of the related org_pages have any Owner Groups, this method
   * leaves the entity untouched — that entity remains admin-only-editable
   * until at least one org_page is populated.
   *
   * @return bool
   *   TRUE if the entity's field_content_organization was updated to a
   *   new value. FALSE if nothing changed — the caller can use this to
   *   skip an unnecessary save().
   */
  public function populateOwnerGroupsFromOrgPage(EntityInterface $entity): bool {
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
    $collected = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($org_nids) as $org_page) {
      if (!$org_page->hasField('field_content_organization')) {
        continue;
      }
      foreach ($org_page->get('field_content_organization')->getValue() as $item) {
        $tid = (int) ($item['target_id'] ?? 0);
        if ($tid) {
          $collected[$tid] = TRUE;
        }
      }
    }
    if (empty($collected)) {
      return FALSE;
    }
    $new_tids = array_keys($collected);
    sort($new_tids);
    $entity->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $new_tids)
    );
    return TRUE;
  }

}

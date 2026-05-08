<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Checks and syncs org-based access for content entities.
 */
class OrgAccessChecker {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
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
      $cache[$uid] = $user
        ? array_map('intval', array_column($user->get('field_user_org')->getValue(), 'target_id'))
        : [];
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
   * Syncs field_organizations to field_content_organization on the entity.
   *
   * Resolves org_page references to user_organization term references,
   * including all ancestor org TIDs. Pure read-of-Organizations →
   * write-to-Owner-Groups; never auto-assigns the Organization itself
   * (that is the form pre-fill's job in entity_prepare_form).
   */
  public function syncContentOrganization(EntityInterface $entity): void {
    if (!$entity->hasField('field_content_organization')) {
      return;
    }

    $org_nids = [];

    // org_page nodes represent an org themselves.
    if ($entity instanceof NodeInterface && $entity->bundle() === 'org_page' && $entity->id()) {
      $org_nids[] = (int) $entity->id();
    }

    if ($entity->hasField('field_organizations')) {
      foreach ($entity->get('field_organizations') as $item) {
        if ($item->target_id) {
          $org_nids[] = (int) $item->target_id;
        }
      }
    }

    if (empty($org_nids)) {
      return;
    }

    $term_ids = $this->getTermIdsByOrgNidsWithAncestors(array_unique($org_nids));

    $entity->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $term_ids)
    );
  }

  /**
   * Pre-fills field_content_organization on the entity from the current
   * user's field_user_org terms plus their taxonomy ancestors.
   *
   * Called from entity_prepare_form. Source of truth is the user's Org
   * Taxonomy assignment — never the entity's field_organizations.
   * Skipped when the user has no org assigned or when the field already
   * has a value (don't override editor / backfill choices).
   */
  public function populateOwnerGroupsFromCurrentUser(EntityInterface $entity): void {
    if (!$entity->hasField('field_content_organization')) {
      return;
    }
    if (!$entity->get('field_content_organization')->isEmpty()) {
      return;
    }
    $user_tids = $this->getUserOrgTids($this->currentUser);
    if (empty($user_tids)) {
      return;
    }
    $term_ids = $this->walkTermAncestors($user_tids);
    $entity->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $term_ids)
    );
  }

  /**
   * Returns user_organization term IDs for the given org_page NIDs, plus all
   * ancestors via the taxonomy term parent hierarchy.
   *
   * Used by the drush moab backfill (via syncContentOrganization). Walks
   * the term `parent` chain so the access decision matches the
   * entity_reference_tree widget's `auto_check_ancestors` behavior.
   */
  private function getTermIdsByOrgNidsWithAncestors(array $nids): array {
    if (empty($nids)) {
      return [];
    }
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $start_tids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'user_organization')
      ->condition('field_state_organization', $nids, 'IN')
      ->execute();
    if (empty($start_tids)) {
      return [];
    }
    return $this->walkTermAncestors(array_map('intval', array_values($start_tids)));
  }

  /**
   * BFS over the taxonomy term `parent` chain. Returns the input TIDs plus
   * every reachable ancestor TID, deduplicated.
   */
  private function walkTermAncestors(array $tids): array {
    $seen = array_fill_keys($tids, TRUE);
    $queue = $tids;
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    while (!empty($queue)) {
      $batch = array_splice($queue, 0, 50);
      foreach ($term_storage->loadMultiple($batch) as $term) {
        foreach ($term->get('parent')->getValue() as $parent) {
          $parent_tid = (int) $parent['target_id'];
          if ($parent_tid && !isset($seen[$parent_tid])) {
            $seen[$parent_tid] = TRUE;
            $queue[] = $parent_tid;
          }
        }
      }
    }
    return array_map('intval', array_keys($seen));
  }

}

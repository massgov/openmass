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
   * Pre-fills field_organizations from the current user's first
   * user_organization term → field_state_organization (org_page) when the
   * entity has no Organization(s) value yet.
   *
   * Called from entity_prepare_form so the editor sees the inherited org
   * before pressing Save. Not called from entity_presave: by save time the
   * form widget already carries the value (or the editor deliberately
   * cleared it). Skipped if the user has no org assigned or the term has
   * no field_state_organization mapping.
   */
  public function autoAssignFromCreator(EntityInterface $entity): void {
    if (!$entity->hasField('field_organizations') || !$entity->get('field_organizations')->isEmpty()) {
      return;
    }
    $tids = $this->getUserOrgTids($this->currentUser);
    if (empty($tids)) {
      return;
    }
    $first_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tids[0]);
    if (!$first_term || !$first_term->hasField('field_state_organization')) {
      return;
    }
    $org_page_nid = (int) ($first_term->get('field_state_organization')->target_id ?? 0);
    if ($org_page_nid) {
      $entity->set('field_organizations', [['target_id' => $org_page_nid]]);
    }
  }

  /**
   * Returns user_organization term IDs for the given org_page NIDs, plus all
   * ancestors via the taxonomy term parent hierarchy.
   *
   * Walks the term `parent` chain (not org_page `field_parent`) so the access
   * decision matches the entity_reference_tree widget's `auto_check_ancestors`
   * behavior on the editor form.
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

    $seen = array_fill_keys($start_tids, TRUE);
    $queue = array_values($start_tids);

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

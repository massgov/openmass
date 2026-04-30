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
   * including all ancestor org TIDs.
   */
  public function syncContentOrganization(EntityInterface $entity): void {
    if (!$entity->hasField('field_content_organization')) {
      return;
    }

    // For brand-new entities with no Organization picked yet, auto-assign
    // the creator's first user_organization → org_page so org-based access
    // applies from day one.
    if ($entity->isNew()
      && $entity->hasField('field_organizations')
      && $entity->get('field_organizations')->isEmpty()
    ) {
      $this->autoAssignFromCreator($entity);
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

    $all_nids = $this->collectAncestorNids(array_unique($org_nids));
    $term_ids = $this->getTermIdsByOrgNids($all_nids);

    $entity->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $term_ids)
    );
  }

  /**
   * Pre-fills field_organizations on a new entity from the current user's
   * first user_organization term. Skipped if the user has no org assigned
   * or the term has no field_state_organization mapping.
   */
  private function autoAssignFromCreator(EntityInterface $entity): void {
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
   * Walks field_parent upward and returns all ancestor NIDs including the input.
   */
  private function collectAncestorNids(array $nids): array {
    $seen = array_fill_keys($nids, TRUE);
    $queue = $nids;
    $node_storage = $this->entityTypeManager->getStorage('node');

    while (!empty($queue)) {
      $batch = array_splice($queue, 0, 50);
      foreach ($node_storage->loadMultiple($batch) as $node) {
        if (!$node->hasField('field_parent') || $node->get('field_parent')->isEmpty()) {
          continue;
        }
        $parent_nid = (int) $node->get('field_parent')->target_id;
        if ($parent_nid && !isset($seen[$parent_nid])) {
          $seen[$parent_nid] = TRUE;
          $queue[] = $parent_nid;
        }
      }
    }

    return array_keys($seen);
  }

  /**
   * Returns user_organization term IDs for the given org_page NIDs.
   *
   * Matches terms whose field_state_organization references any of the NIDs.
   */
  private function getTermIdsByOrgNids(array $nids): array {
    if (empty($nids)) {
      return [];
    }
    return array_values(
      $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'user_organization')
        ->condition('field_state_organization', $nids, 'IN')
        ->execute()
    );
  }

}

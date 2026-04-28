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
   * Returns the user's org taxonomy term ID, or 0 if none assigned.
   */
  public function getUserOrgTid(AccountInterface $account): int {
    $cache = &drupal_static(__METHOD__);
    $uid = $account->id();
    if (!isset($cache[$uid])) {
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      $cache[$uid] = (int) ($user?->get('field_user_org')?->target_id ?? 0);
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
    $user_tid = $this->getUserOrgTid($account);
    if (!$user_tid) {
      return TRUE;
    }
    $entity_tids = $this->getEntityOrgTids($entity);
    if (empty($entity_tids)) {
      return TRUE;
    }
    return in_array((string) $user_tid, array_map('strval', $entity_tids), TRUE);
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

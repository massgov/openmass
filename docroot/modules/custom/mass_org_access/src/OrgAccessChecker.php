<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Checks and syncs org-based access for content entities.
 */
class OrgAccessChecker {

  /**
   * Node bundles that store organizations on a bundle-specific field.
   *
   * The mass_validation module syncs these into field_organizations on presave.
   */
  private const BUNDLE_ORG_FIELD = [
    'binder' => 'field_binder_ref_organization',
    'decision' => 'field_decision_ref_organization',
    'person' => 'field_person_ref_org',
  ];

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
      $cache[$uid] = $user ? $this->referenceTargetIds($user, 'field_user_org') : [];
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
    $org_nids = $this->referenceTargetIds($entity, 'field_organizations');
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

  /**
   * Sets field_content_organization to the current user's org terms.
   *
   * Reads the editor's field_user_org terms, walks taxonomy ancestors,
   * and writes the union. Skipped when the user has no org assigned or
   * the field already has a value. The caller decides when to invoke
   * this — the entity_prepare_form hook calls it only for new content;
   * existing content is left to drush moab.
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
   * Returns org_page NIDs from the user's Default organizations field.
   */
  public function getDefaultOrganizationNids(AccountInterface $account): array {
    $user = $this->loadUser($account);
    if (!$user || !$user->hasField('field_default_organizations')) {
      return [];
    }
    return $this->referenceTargetIds($user, 'field_default_organizations');
  }

  /**
   * Default org_page NIDs, falling back to permission-group mapping.
   *
   * Used for new media.document forms when the user has not set defaults.
   */
  public function getDefaultOrganizationNidsWithFallback(AccountInterface $account): array {
    $nids = $this->getDefaultOrganizationNids($account);
    if (!empty($nids)) {
      return $nids;
    }
    return $this->resolveOrgPageNidsFromUserOrgTerms($this->getUserOrgTids($account));
  }

  /**
   * Returns label term IDs from the user's Default labels field.
   */
  public function getDefaultLabelTids(AccountInterface $account): array {
    $user = $this->loadUser($account);
    if (!$user || !$user->hasField('field_default_labels')) {
      return [];
    }
    return $this->referenceTargetIds($user, 'field_default_labels');
  }

  /**
   * Maps user_organization term IDs to org_page NIDs via field_state_organization.
   *
   * @param int[] $term_ids
   *   user_organization term IDs.
   *
   * @return int[]
   *   org_page node IDs, deduplicated.
   */
  public function resolveOrgPageNidsFromUserOrgTerms(array $term_ids): array {
    if (empty($term_ids)) {
      return [];
    }
    $collected = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($term_ids);
    foreach ($terms as $term) {
      if (!$term->hasField('field_state_organization') || $term->get('field_state_organization')->isEmpty()) {
        continue;
      }
      $nid = (int) $term->get('field_state_organization')->target_id;
      if ($nid) {
        $collected[$nid] = $nid;
      }
    }
    return array_values($collected);
  }

  /**
   * Pre-fills organization and label fields on new content from user defaults.
   *
   * Does not run on existing entities or when target fields already have values.
   */
  public function applyDefaultsToNewEntity(FieldableEntityInterface $entity, AccountInterface $account): void {
    if (!$entity->isNew()) {
      return;
    }

    $org_nids = $entity instanceof MediaInterface && $entity->bundle() === 'document'
      ? $this->getDefaultOrganizationNidsWithFallback($account)
      : $this->getDefaultOrganizationNids($account);

    if (!empty($org_nids)) {
      $org_field = $this->getOrganizationFieldName($entity);
      if ($org_field && !$this->fieldHasPopulatedReferences($org_field, $entity)) {
        $entity->set(
          $org_field,
          array_map(fn(int $nid) => ['target_id' => $nid], $org_nids)
        );
      }
    }

    $label_tids = $this->getDefaultLabelTids($account);
    if (empty($label_tids)) {
      return;
    }

    $label_field = $this->getLabelFieldName($entity);
    if ($label_field && !$this->fieldHasPopulatedReferences($label_field, $entity)) {
      $entity->set(
        $label_field,
        array_map(fn(int $tid) => ['target_id' => $tid], $label_tids)
      );
    }
  }

  /**
   * Sets field_default_organizations from permission groups when still empty.
   *
   * Used by the deploy hook for existing active users.
   *
   * @return bool
   *   TRUE if the user entity was updated (caller should save).
   */
  public function populateUserDefaultOrganizationsFromPermissionGroups(UserInterface $user): bool {
    if (!$user->hasField('field_default_organizations')) {
      return FALSE;
    }
    if ($this->fieldHasPopulatedReferences('field_default_organizations', $user)) {
      return FALSE;
    }

    $nids = $this->resolveOrgPageNidsFromUserOrgTerms($this->getUserOrgTids($user));
    if (empty($nids)) {
      return FALSE;
    }

    sort($nids);
    $existing = $this->referenceTargetIds($user, 'field_default_organizations');
    sort($existing);
    if ($existing === $nids) {
      return FALSE;
    }

    $user->set(
      'field_default_organizations',
      array_map(fn(int $nid) => ['target_id' => $nid], $nids)
    );
    return TRUE;
  }

  /**
   * Returns the editor-facing organization reference field for an entity.
   */
  public function getOrganizationFieldName(FieldableEntityInterface $entity): ?string {
    if ($entity instanceof NodeInterface) {
      $bundle = $entity->bundle();
      if ($bundle === 'executive_order') {
        return NULL;
      }
      if (isset(self::BUNDLE_ORG_FIELD[$bundle])) {
        return self::BUNDLE_ORG_FIELD[$bundle];
      }
      if ($entity->hasField('field_organizations')) {
        return 'field_organizations';
      }
      return NULL;
    }
    if ($entity instanceof MediaInterface && $entity->bundle() === 'document') {
      return $entity->hasField('field_organizations') ? 'field_organizations' : NULL;
    }
    return NULL;
  }

  /**
   * Returns the label reference field for an entity, if any.
   */
  public function getLabelFieldName(FieldableEntityInterface $entity): ?string {
    if ($entity->hasField('field_reusable_label')) {
      return 'field_reusable_label';
    }
    if ($entity instanceof MediaInterface && $entity->hasField('field_document_label')) {
      return 'field_document_label';
    }
    return NULL;
  }

  /**
   * Loads the full user entity for field reads.
   */
  private function loadUser(AccountInterface $account): ?UserInterface {
    if ($account instanceof UserInterface) {
      return $account;
    }
    if ($account->isAnonymous()) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    return $user instanceof UserInterface ? $user : NULL;
  }

  /**
   * Target IDs from an entity reference field (non-empty values only).
   *
   * @return int[]
   *   Referenced entity IDs, deduplicated.
   */
  private function referenceTargetIds(FieldableEntityInterface $entity, string $field_name): array {
    if (!$entity->hasField($field_name)) {
      return [];
    }
    $ids = [];
    foreach ($entity->get($field_name) as $item) {
      if (!$item->isEmpty()) {
        $ids[(int) $item->target_id] = (int) $item->target_id;
      }
    }
    return array_values($ids);
  }

  /**
   * Whether a reference field has at least one non-empty target_id.
   */
  private function fieldHasPopulatedReferences(string $field_name, FieldableEntityInterface $entity): bool {
    return $this->referenceTargetIds($entity, $field_name) !== [];
  }

  /**
   * BFS over the taxonomy term `parent` chain.
   *
   * Returns the input TIDs plus every reachable ancestor TID,
   * deduplicated. Used by form pre-fill — the backfill path does not walk
   * ancestors because org_page values already include them (see
   * REQUIREMENTS.md Section C/D).
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

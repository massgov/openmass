<?php

declare(strict_types=1);

namespace Drupal\mass_utility\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Hook implementations + helpers for the User Defaults feature.
 *
 * Pre-fills new content with the editor's default organizations and
 * labels from their user profile (field_default_organizations and
 * field_default_labels). One OOP hook class per feature — owns both
 * the hook wiring and the domain logic.
 */
class UserDefaultsHooks {

  /**
   * Node bundles that store organizations on a bundle-specific field.
   *
   * The mass_validation module syncs these into field_organizations on
   * presave. Pre-fill must target the bundle-specific field directly.
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
   * Pre-fills new entities with the current user's default org and labels.
   *
   * Only runs on new content — existing entities keep whatever they had.
   */
  #[Hook('entity_prepare_form')]
  public function entityPrepareForm(EntityInterface $entity, string $operation, FormStateInterface $form_state): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    if ($this->currentUser->isAnonymous()) {
      return;
    }
    $this->applyToNewEntity($entity, $this->currentUser);
  }

  /**
   * Pre-fills organization and label fields on new content from user defaults.
   *
   * Does not run on existing entities or when target fields already have
   * values. media.document additionally falls back to the permission-group
   * mapping when the user has no defaults set.
   */
  public function applyToNewEntity(FieldableEntityInterface $entity, AccountInterface $account): void {
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
   * Returns org_page NIDs from the user's Default organizations field.
   */
  private function getDefaultOrganizationNids(AccountInterface $account): array {
    $user = $this->loadUser($account);
    if (!$user || !$user->hasField('field_default_organizations')) {
      return [];
    }
    return $this->referenceTargetIds($user, 'field_default_organizations');
  }

  /**
   * Default org_page NIDs, falling back to permission-group mapping.
   *
   * Used for new media.document forms when the user has not set defaults
   * — keeps the historical behavior where the editor's org pre-fills the
   * Organizations field.
   */
  private function getDefaultOrganizationNidsWithFallback(AccountInterface $account): array {
    $nids = $this->getDefaultOrganizationNids($account);
    if (!empty($nids)) {
      return $nids;
    }
    $user = $this->loadUser($account);
    if (!$user) {
      return [];
    }
    return $this->resolveOrgPageNidsFromUserOrgTerms($this->referenceTargetIds($user, 'field_user_org'));
  }

  /**
   * Returns label term IDs from the user's Default labels field.
   */
  private function getDefaultLabelTids(AccountInterface $account): array {
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
  private function resolveOrgPageNidsFromUserOrgTerms(array $term_ids): array {
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
   * Returns the editor-facing organization reference field for an entity.
   */
  private function getOrganizationFieldName(FieldableEntityInterface $entity): ?string {
    if ($entity instanceof NodeInterface) {
      $bundle = $entity->bundle();
      if ($bundle === 'executive_order') {
        return NULL;
      }
      if (isset(self::BUNDLE_ORG_FIELD[$bundle])) {
        return self::BUNDLE_ORG_FIELD[$bundle];
      }
      return $entity->hasField('field_organizations') ? 'field_organizations' : NULL;
    }
    if ($entity instanceof MediaInterface && $entity->bundle() === 'document') {
      return $entity->hasField('field_organizations') ? 'field_organizations' : NULL;
    }
    return NULL;
  }

  /**
   * Returns the label reference field for an entity, if any.
   */
  private function getLabelFieldName(FieldableEntityInterface $entity): ?string {
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

}

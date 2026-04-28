<?php

namespace Drupal\mass_org_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_org_access\OrgAccessChecker;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for mass_org_access.
 */
class MassOrgAccessHooks {

  public function __construct(
    private readonly OrgAccessChecker $orgAccessChecker,
  ) {}

  /**
   * Restricts node UPDATE/DELETE to users within the node's organization.
   *
   * VIEW is always neutral — editors may view and clone any content.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResult {
    if (!in_array($operation, ['update', 'delete'], TRUE)) {
      return AccessResult::neutral();
    }
    if ($account->hasPermission('bypass org access')) {
      return AccessResult::neutral();
    }
    $entity_tids = $this->orgAccessChecker->getEntityOrgTids($node);
    if (empty($entity_tids)) {
      return AccessResult::neutral();
    }
    if ($this->orgAccessChecker->userHasOrgAccess($account, $node)) {
      return AccessResult::neutral();
    }
    return AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

  /**
   * Same restriction for media entities (e.g. media.document).
   */
  #[Hook('media_access')]
  public function mediaAccess(EntityInterface $media, string $operation, AccountInterface $account): AccessResult {
    if (!in_array($operation, ['update', 'delete'], TRUE)) {
      return AccessResult::neutral();
    }
    if ($account->hasPermission('bypass org access')) {
      return AccessResult::neutral();
    }
    $entity_tids = $this->orgAccessChecker->getEntityOrgTids($media);
    if (empty($entity_tids)) {
      return AccessResult::neutral();
    }
    if ($this->orgAccessChecker->userHasOrgAccess($account, $media)) {
      return AccessResult::neutral();
    }
    return AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheableDependency($media);
  }

  /**
   * Syncs field_content_organization on every node/media save.
   *
   * This keeps the denormalized org TIDs (including ancestors) up to date.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface || $entity->getEntityTypeId() === 'media') {
      $this->orgAccessChecker->syncContentOrganization($entity);
    }
  }

  /**
   * Adds a validation callback for cross-organization save attempts.
   *
   * Shows a clear error when an editor tries to save a node outside their
   * organization. The edit form itself remains accessible for viewing and
   * cloning.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->isNew()) {
      return;
    }
    $form['#validate'][] = [self::class, 'validateOrgAccess'];
  }

  /**
   * Form #validate callback enforcing cross-org save protection.
   *
   * Used as [self::class, 'validateOrgAccess'] (serializable) so paragraph
   * AJAX rebuilds don't break on closure serialization.
   */
  public static function validateOrgAccess(array &$form, FormStateInterface $form_state): void {
    $account = \Drupal::currentUser();
    if ($account->hasPermission('bypass org access')) {
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    /** @var \Drupal\mass_org_access\OrgAccessChecker $checker */
    $checker = \Drupal::service('mass_org_access.org_access_checker');
    $entity_tids = $checker->getEntityOrgTids($entity);
    if (empty($entity_tids)) {
      return;
    }
    if ($checker->userHasOrgAccess($account, $entity)) {
      return;
    }
    $org_labels = [];
    if ($entity->hasField('field_organizations')) {
      foreach ($entity->get('field_organizations') as $item) {
        if ($item->entity) {
          $org_labels[] = $item->entity->label();
        }
      }
    }
    $org_list = $org_labels ? implode(', ', $org_labels) : t('another organization');
    $form_state->setErrorByName('', t(
      'You do not have permission to save this content. It belongs to @org. Contact your administrator if you need access.',
      ['@org' => $org_list]
    ));
  }

}

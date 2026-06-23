<?php

namespace Drupal\mass_org_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mass_org_access\OrgAccessChecker;
use Drupal\mass_org_access\OrgAccessSettings;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Hook implementations for mass_org_access.
 */
class MassOrgAccessHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly OrgAccessChecker $orgAccessChecker,
    private readonly MessengerInterface $messenger,
    private readonly OrgAccessSettings $settings,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Restricts node UPDATE/DELETE to users within the node's organization.
   *
   * VIEW is always neutral — editors may view and clone any content.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    return $this->checkAccess($node, $operation, $account);
  }

  /**
   * Same restriction for media entities (e.g. media.document).
   */
  #[Hook('media_access')]
  public function mediaAccess(EntityInterface $media, string $operation, AccountInterface $account): AccessResultInterface {
    return $this->checkAccess($media, $operation, $account);
  }

  /**
   * Locks field_user_org to users with `administer users`.
   *
   * Prevents an editor from self-assigning organizations on
   * `/user/UID/edit`. View access stays neutral so editors can still
   * see the org listed on user pages. field_content_organization is
   * not restricted here — anyone who can edit the host entity may edit
   * the widget.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field->getName() !== 'field_user_org') {
      return AccessResult::neutral();
    }
    if ($operation === 'view') {
      return AccessResult::neutral();
    }
    return AccessResult::forbiddenIf(!$account->hasPermission('administer users'))
      ->cachePerPermissions();
  }

  /**
   * Shared org-based access logic for nodes and media.
   *
   * Decisions depend on the user's field_user_org, so the result must
   * invalidate when that user is updated — the user:UID cache tag handles it.
   */
  private function checkAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if (!in_array($operation, ['update', 'delete'], TRUE)) {
      return AccessResult::neutral();
    }
    if (!$this->settings->isEnforcementEnabled()) {
      return AccessResult::neutral();
    }
    if ($account->hasPermission('bypass org access')) {
      return AccessResult::neutral();
    }

    $forbidden = AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheableDependency($entity)
      ->addCacheTags(['user:' . $account->id()]);

    if (empty($this->orgAccessChecker->getUserOrgTids($account))) {
      return $forbidden;
    }

    // Per REQUIREMENTS.md "Done when" point 6: entities with no Owner
    // Groups are editable only by admins and content admins. The bypass
    // check above already let those roles through, so any user reaching
    // this point with an empty Owner Groups field is denied.
    $entity_tids = $this->orgAccessChecker->getEntityOrgTids($entity);
    if (empty($entity_tids)) {
      return $forbidden;
    }
    if ($this->orgAccessChecker->userHasOrgAccess($account, $entity)) {
      return AccessResult::neutral();
    }
    return $forbidden;
  }

  /**
   * Hides the autocomplete tags input and shows a read-only list instead.
   *
   * The autocomplete input stays in the DOM (display:none) so it still
   * submits with whatever the "Browse organizations" popup set, but
   * users cannot type or delete tokens by hand — a stray backspace on
   * a parent-org token would otherwise silently strip access for
   * everyone in that group. A sibling list markup shows the selected
   * terms one per line so a long list is fully visible. The list is
   * server-rendered from the entity's referenced terms; after saving
   * the form Drupal rebuilds it with the fresh values.
   *
   * Release 1 visibility: only administrators (`view permission groups field`,
   * which Content Administrators do NOT have) and anyone editing an
   * Organization page may see the field. For everyone else — including Content
   * Administrators on non-Organization bundles — it is hidden with CSS. The
   * field stays in the form so the value still derives from Organization(s)
   * (via the oog_from_organizations JS) and saves, keeping the org-taxonomy
   * permission data populated; the user just never sees or touches it.
   * Release 2 can drop the wrapper to restore visibility.
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    if ($context['items']->getName() !== 'field_content_organization') {
      return;
    }
    $entity = $context['items']->getEntity();
    $can_see = $this->currentUser->hasPermission('view permission groups field')
      || $entity->bundle() === 'org_page';
    // Debug mode (settings form) reveals the field to everyone so editors can
    // verify which organizations are attached; otherwise authors/editors get
    // the CSS-hiding wrapper while the field still submits its derived value.
    if (!$can_see && !$this->settings->isDebugModeEnabled()) {
      $field_widget_complete_form['#prefix'] = '<div class="oog-hidden-from-author">';
      $field_widget_complete_form['#suffix'] = '</div>';
    }
    if (!isset($field_widget_complete_form['widget']['target_id'])) {
      return;
    }
    $widget = &$field_widget_complete_form['widget']['target_id'];
    $widget['#attributes']['readonly'] = 'readonly';
    $widget['#wrapper_attributes']['class'][] = 'oog-readonly-source-hidden';

    $labels = [];
    foreach ($context['items']->referencedEntities() as $term) {
      if ($term instanceof EntityInterface) {
        $labels[] = htmlspecialchars($term->label(), ENT_QUOTES);
      }
    }
    $list_html = $labels
      ? '<ul>' . implode('', array_map(fn($l) => '<li>' . $l . '</li>', $labels)) . '</ul>'
      : '<em>(none assigned)</em>';
    // Render via #type item so Drupal's form-element template wraps the
    // markup with a real <label> + <div class="description">; the
    // styling stays consistent with every other field on the page.
    $field_widget_complete_form['oog_display'] = [
      '#type' => 'item',
      '#title' => $widget['#title'] ?? NULL,
      '#description' => $widget['#description'] ?? NULL,
      '#markup' => '<div class="oog-readonly-display">' . $list_html . '</div>',
      '#wrapper_attributes' => ['class' => ['oog-readonly-wrapper']],
      '#weight' => -10,
    ];
    $field_widget_complete_form['#attached']['library'][] = 'mass_org_access/oog_readonly_display';
    // When the user adds an organization in field_organizations on the
    // same form, JS pulls the matching curated Owner Groups from the
    // org_page and appends them here. Only loaded alongside the OOG
    // widget, which already implies the user has edit access to it.
    $field_widget_complete_form['#attached']['library'][] = 'mass_org_access/oog_from_organizations';
    // Pass the current user's own permission groups so the same JS can warn
    // before a save that would strip the author's access (their groups not
    // among the content's). Gated on the enforcement switch so Release 1 (gate
    // off) shows no warning — nothing is locked yet. Also off for bypass users
    // and users with no groups (the login warning already covers the latter).
    $user_tids = $this->orgAccessChecker->getUserOrgTids($this->currentUser);
    $warn_on_lockout = $this->settings->isEnforcementEnabled()
      && !$this->currentUser->hasPermission('bypass org access')
      && !empty($user_tids);
    $field_widget_complete_form['#attached']['drupalSettings']['massOrgAccess'] = [
      'userPermissionGroupTids' => array_values($user_tids),
      'warnOnSelfLockout' => $warn_on_lockout,
      // Host entity context so the lookup endpoint can bind access to it
      // (update access for an existing entity, create access for a new one).
      'hostEntityType' => $entity->getEntityTypeId(),
      'hostEntityId' => $entity->isNew() ? NULL : (string) $entity->id(),
      'hostBundle' => $entity->bundle(),
    ];
  }

  /**
   * Defense-in-depth validation callback on node forms.
   *
   * The node_access hook already denies update/delete on out-of-org
   * content, and RouteSubscriber narrows the side-door routes;
   * reaching form validation would require a code path that bypassed
   * both. If that happens, the callback surfaces a clear error
   * instead of silently letting the save through.
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
    /** @var \Drupal\mass_org_access\OrgAccessSettings $settings */
    $settings = \Drupal::service('mass_org_access.settings');
    if (!$settings->isEnforcementEnabled()) {
      return;
    }
    $account = \Drupal::currentUser();
    if ($account->hasPermission('bypass org access')) {
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    /** @var \Drupal\mass_org_access\OrgAccessChecker $checker */
    $checker = \Drupal::service('mass_org_access.org_access_checker');

    if (empty($checker->getUserOrgTids($account))) {
      $form_state->setErrorByName('', t(
        'Your account is not associated with any organization. Please contact your site administrator to assign an organization before saving content.'
      ));
      return;
    }

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

  /**
   * Derives Permission Groups server-side, then warns on self-lockout.
   *
   * The authoritative derivation lives here, not in the client-side JS: on
   * every real (non-syncing) save we recompute field_content_organization from
   * the entity's Organization(s) so the saved value cannot be left stale or
   * empty by a fast submit, disabled JS, a failed lookup, or a crafted POST —
   * and it covers media and bulk/programmatic saves, not just node edit forms.
   * The JS stays as a live preview. Runs after mass_validation, which has by
   * then mirrored the bundle-specific org fields into field_organizations.
   *
   * Then, if the resulting Permission Groups no longer include any of the
   * saving user's own, a non-blocking warning is shown (the save still goes
   * through) so they can fix the Organization(s) if it was a mistake.
   */
  #[Hook('entity_presave', order: new OrderAfter(modules: ['mass_validation']))]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof ContentEntityInterface || $entity->isSyncing()) {
      return;
    }
    $this->orgAccessChecker->reconcileOwnerGroupsFromOrganizations($entity);
    $this->warnOnSelfLockout($entity);
  }

  /**
   * Warns the saving user when the derived Permission Groups exclude their own.
   *
   * Gated on the enforcement switch (MASS_ORG_ACCESS_ENFORCE / the
   * mass_org_access.enforce State key): with the gate off — as in Release 1 —
   * nothing is actually locked, so the warning would only be noise. Also skips
   * entities without the field, users with `bypass org access` (never locked
   * out), and users with no Permission Groups of their own (the login warning
   * already covers them).
   */
  private function warnOnSelfLockout(EntityInterface $entity): void {
    if (!$this->settings->isEnforcementEnabled()) {
      return;
    }
    if (!$entity->hasField('field_content_organization')) {
      return;
    }
    if ($this->currentUser->hasPermission('bypass org access')) {
      return;
    }
    $user_tids = $this->orgAccessChecker->getUserOrgTids($this->currentUser);
    if (empty($user_tids)) {
      return;
    }
    if (array_intersect($user_tids, $this->orgAccessChecker->getEntityOrgTids($entity))) {
      return;
    }
    $this->messenger->addWarning($this->t(
      'You have lost access to this content. If this was a mistake, contact your administrator.'
    ));
  }

  /**
   * Warns editors/authors at login when their account is not assigned an org.
   *
   * They will be denied any update/delete by checkAccess(), so this message
   * tells them why and who to contact.
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    if (!$this->settings->isEnforcementEnabled()) {
      return;
    }
    if ($account->hasPermission('bypass org access')) {
      return;
    }
    $editorial_roles = ['editor', 'author'];
    if (empty(array_intersect($editorial_roles, $account->getRoles()))) {
      return;
    }
    if (!$account->hasField('field_user_org') || $account->get('field_user_org')->isEmpty()) {
      $this->messenger->addWarning($this->t(
        'Your account is not associated with any organization. You will not be able to edit or delete content until an organization is assigned. Please contact your site administrator.'
      ));
    }
  }

}

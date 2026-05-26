<?php

namespace Drupal\mass_org_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
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
   * Pre-fills Permission Groups on new entities only.
   *
   * Reads the creator's field_user_org plus ancestors and writes the
   * union to field_content_organization before the form widgets are
   * built, so the new content reflects the author's own org. Existing
   * entities are populated exclusively by drush moab — opening an
   * un-backfilled legacy entity must not silently re-stamp it with the
   * current editor's org.
   *
   * Uses entity_prepare_form (not form_alter) because the widget reads
   * its default value from the entity during EntityFormDisplay::buildForm,
   * which runs before form_alter fires.
   */
  #[Hook('entity_prepare_form')]
  public function entityPrepareForm(EntityInterface $entity, string $operation, FormStateInterface $form_state): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    // Pre-fill only for new content (creator's own org). Existing
    // entities are populated exclusively by drush moab — if they are
    // still empty when an editor opens the form, they remain empty and
    // stay admin-only-editable.
    if (!$entity->isNew()) {
      return;
    }
    $this->orgAccessChecker->populateOwnerGroupsFromCurrentUser($entity);
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
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    if ($context['items']->getName() !== 'field_content_organization') {
      return;
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

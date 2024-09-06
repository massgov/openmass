<?php

namespace Drupal\mass_fields\FormAlter;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TopicPageFormAlter.
 *
 * Controls access to topic fields.
 *
 * This is an implementation of the Class Resolver to encapsulate the code for
 * form alters used in mass_fields_form_node_topic_page_edit_form_alter().
 */
class TopicPageFormAlter implements ContainerInjectionInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $currentUser;

  /**
   * Class TopicPageFormAlter constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Controls access to topic fields.
   *
   * Hide the listed fields for users which don't have
   * 'create topic_page content' permissions.
   *
   * @param array $form
   *   The form array being built.
   */
  public function topicFieldsControlAccess(array &$form, FormStateInterface $form_state): void {
    $can_create_topics = $this->currentUser->hasPermission('create topic_page content');
    $this->adminFieldsAccess($form, $can_create_topics);
    $this->organizationsFieldAccess($form, $can_create_topics, $form_state);
  }

  /**
   * Controls access to admin fields.
   *
   * Hides the listed fields for users who don't have
   * 'create topic_page content' permissions.
   *
   * @param array $form
   *   The form array being built.
   * @param bool $can_create_topics
   *   Indicates whether the user has 'create topic_page content' permissions.
   */
  private function adminFieldsAccess(array &$form, bool $can_create_topics): void {
    $fields_to_restrict_access_if_no_create_topic_page_perm = [
      'field_restrict_orgs_field',
      'field_hide_feedback_component',
    ];

    foreach ($fields_to_restrict_access_if_no_create_topic_page_perm as $field) {
      if (!isset($form[$field])) {
        continue;
      }

      $form[$field]['#access'] = $can_create_topics;
    }
  }

  /**
   * Controls access to organizations field.
   *
   * Show the field_organizations for not admins, but keep it disabled.
   *
   * @param array $form
   *   The form array being built.
   * @param bool $can_create_topics
   *   A boolean indicating if the user has 'create topic_page content'
   *   permissions.
   */
  private function organizationsFieldAccess(array &$form, bool $can_create_topics, FormStateInterface $form_state): void {
    if (!isset($form['field_organizations'])) {
      return;
    }

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }

    $node = $form_object->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }

    if (!$node->hasField('field_restrict_orgs_field')) {
      return;
    }

    // This logic should not be executed if a user has access to edit the Topic
    // Page.
    if ($can_create_topics) {
      return;
    }

    // Do nothing, field value not defined, default behaviour.
    // Field_restrict_orgs_field possible values:
    // - "1", access to field_organizations restricted.
    // - "0" access to field_organizations unrestricted.
    // - null, not defined, access to field_organizations unrestricted.
    if (is_null($node->get('field_restrict_orgs_field')->value)) {
      return;
    }

    // Do nothing if links management restricted globally for that Topic Page.
    $link_management_restricted = $node->field_restrict_link_management->value ?? FALSE;
    if ($link_management_restricted) {
      return;
    }

    $orgs_field_is_access_restricted_by_admin = (bool) $node->get('field_restrict_orgs_field')->value;
    $form['field_organizations']['#disabled'] = $orgs_field_is_access_restricted_by_admin;

    if ($orgs_field_is_access_restricted_by_admin) {
      // Remove validations created by conditional_fields when we don't need it.
      $index = array_search('conditional_fields_form_after_build', $form["#after_build"]);
      if (is_int($index)) {
        unset($form['#after_build'][$index]);
      }
    }

  }

}

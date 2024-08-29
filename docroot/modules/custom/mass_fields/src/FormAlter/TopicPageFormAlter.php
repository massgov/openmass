<?php

namespace Drupal\mass_fields\FormAlter;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
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
  public function topicFieldsControlAccess(array &$form): void {
    $fields_to_restrict_access_if_no_create_topic_page_perm = [
      'field_restrict_orgs_field',
      'field_hide_feedback_component',
    ];

    foreach ($fields_to_restrict_access_if_no_create_topic_page_perm as $field) {
      if (!isset($form[$field])) {
        continue;
      }

      $can_manage_topics = $this->currentUser->hasPermission('create topic_page content');

      $form[$field]['#access'] = $can_manage_topics;
    }

  }

}

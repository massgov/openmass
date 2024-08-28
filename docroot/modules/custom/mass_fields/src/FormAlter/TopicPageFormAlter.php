<?php

namespace Drupal\mass_fields\FormAlter;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TopicPageFormAlter.
 *
 * Controls access to topic fields.
 *
 * @todo Fix problem: add more descriptive documentation.
 */
class TopicPageFormAlter implements ContainerInjectionInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $currentUser;

  /**
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
   * @param array $form
   *   The form array being built.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function topicFieldsControlAccess(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['field_hide_feedback_component'])) {
      return;
    }

    $can_manage_topics = $this->currentUser->hasPermission('create topic_page content');

    $form['field_hide_feedback_component']['#access'] = $can_manage_topics;
  }

}

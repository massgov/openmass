<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_content\Entity\Bundle\node\NodeBundle;
use Drupal\mass_content_moderation\MassModeration;

/**
 * Provides a Mass.gov Utility Module form.
 */
class MoveRedirectsForm extends FormBase {

  /**
   * User must have edit access and the node must be in Trash.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeBundle $node, AccountInterface $account) {
    return AccessResult::allowedIf($node->access('edit', $account) && $node->getModerationState()->getString() == MassModeration::TRASH);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_utility_move_redirects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $items = [];

    $form['list'] = [
      '#type' => 'item_list',
      '#items' => $items,
      '#title' => 'The following items will be moved',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Move'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (mb_strlen($form_state->getValue('message')) < 10) {
      $form_state->setErrorByName('message', $this->t('Message should be at least 10 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

}

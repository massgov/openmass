<?php

namespace Drupal\tfa_unblock\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tfa_unblock\TFAUnblockManager;

/**
 * Admin form of TFA Unblock.
 */
class TFAUnblockAdminForm extends FormBase {

  /**
   * The utility functions service.
   *
   * @var \Drupal\tfa_unblock\TFAUnblockManager
   */
  protected $tfaUnblockManager;

  /**
   * TFAUnblockAdminForm constructor.
   *
   * @param \Drupal\tfa_unblock\TFAUnblockManager $tfaUnblockManager
   *   Utility service.
   */
  public function __construct(TFAUnblockManager $tfaUnblockManager) {

    $this->tfaUnblockManager = $tfaUnblockManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tfa_unblock.tfa_unblock_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_unblock_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get user entries from flood table.
    $entries = $this->tfaUnblockManager->getUsersNotSetup();

    // Manually sort the entries to prioritize users who are blocked.
    $skip_counts = array_column($entries, 'skipped');
    $names = array_column($entries, 'user_name');
    array_multisort($skip_counts, SORT_DESC, $names, SORT_ASC, $entries);

    // Get the user limit from TFA settings.
    $allowed_skips = $this->tfaUnblockManager->getSkipLimit();

    $blocked_message = $this->t('Yes');
    $options = [];
    foreach ($entries as $entry) {
      $skipped = $entry['skipped'];
      $options[$entry['uid']] = [
        'user'        => $entry['user_link'],
        'skipped'     => $skipped,
        'blocked'     => ($skipped >= $allowed_skips ? $blocked_message : ""),
      ];
    }

    $header = [
      'user'    => $this->t('Account name'),
      'skipped' => $this->t('Logins without setting up TFA'),
      'blocked' => $this->t('Blocked'),
    ];

    $prefix = $this->t('The TFA module has been configured to deny access to any user who has not set up two factor authentication after :user_limit tries.',
      [':user_limit' => $allowed_skips]);

    $prefix = '<p>' . $prefix . '</p>';

    $form['table'] = [
      '#type'    => 'tableselect',
      '#header'  => $header,
      '#options' => $options,
      '#empty'   => $this->t('There are no blocked logins at this time.'),
      '#prefix'  => $prefix,
      '#sticky'  => TRUE,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Reset login count'),
    ];

    if (count($entries) == 0) {
      $form['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Checks that one or more user accounts has been selected.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Filter for numeric uids.
    $selected_entries = array_filter($form_state->getValue('table'), 'intval');
    if (empty($selected_entries)) {
      $form_state->setError($form, $this->t('Use the checkboxes to select one or more accounts to unblock.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Quick filter for numeric uids.
    $selected_entries = array_filter($form_state->getValue('table'), 'intval');
    foreach ($selected_entries as $uid) {
      $this->tfaUnblockManager->reset(intval($uid));
    }
  }

}

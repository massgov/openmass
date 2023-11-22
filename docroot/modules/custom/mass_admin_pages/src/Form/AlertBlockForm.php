<?php

namespace Drupal\mass_admin_pages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Contains \Drupal\mass_admin_pages\Form\AlertBlockForm.
 */

/**
 * Configure Site Wide Notification.
 */
class AlertBlockForm extends ConfigFormBase {

  /**
   * Constructs a UpdatesBlockForm object.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_alert_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    $alert_text = \Drupal::state()->get('mass_admin_pages.updates_block_settings.alert_text');

    $form['alert_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Alert content'),
      '#description' => $this->t('Add text to this block to display a message to logged in users (authors and editors) on every page on edit.mass.gov. Use HTML markup to add links or basic styling.'),
      '#default_value' => $alert_text ?? '',
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('mass_admin_pages.updates_block_settings.alert_text', $form_state->getValue('alert_text'));
    parent::submitForm($form, $form_state);

    $tags[] = 'state:mass_admin_pages.updates_block_settings';
    \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_admin_pages.updates_block_settings',
    ];
  }

}

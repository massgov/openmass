<?php

namespace Drupal\mass_admin_pages\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * @file
 * Contains \Drupal\mass_admin_pages\Form\UpdatesBlockForm.
 */

/**
 * Configure Site Wide Notification.
 */
class UpdatesBlockForm extends ConfigFormBase {

  /**
   * Constructs a UpdatesBlockForm object.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_updates_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    $text_field = \Drupal::state()->get('mass_admin_pages.updates_block_settings.text_field');
    $alert_text = \Drupal::state()->get('mass_admin_pages.updates_block_settings.alert_text');

    $form['text_field'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Update content'),
      '#description' => $this->t('Add content to provide important updates for content authors. Use HTML markup to add links or basic styling.  For simple text, be sure to add a P tag around it so that it takes default styling.'),
      '#default_value' => isset($text_field) ? $text_field : '',
    ];

    $form['alert_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Alert content'),
      '#description' => $this->t('Add text to this block to display a message to logged in users (authors and editors) on every page on edit.mass.gov. Use HTML markup to add links or basic styling.'),
      '#default_value' => isset($alert_text) ? $alert_text : '',
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
    \Drupal::state()->set('mass_admin_pages.updates_block_settings.text_field', $form_state->getValue('text_field')['value']);
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
      'mass_admin_pages.updates_block_settings'
    ];
  }

}

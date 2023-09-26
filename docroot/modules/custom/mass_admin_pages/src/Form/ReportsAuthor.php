<?php

namespace Drupal\mass_admin_pages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Contains \Drupal\mass_admin_pages\Form\ReportsAuthor.
 */

/**
 * Configure "Reports for Authors" section.
 */
class ReportsAuthor extends ConfigFormBase {

  /**
   * Constructs a UpdatesBlockForm object.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_reports_author_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    $text_field = \Drupal::state()->get('mass_admin_pages.reports_author_block_settings.text_field');
    $form['text_field'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Report tab content'),
      '#default_value' => isset($text_field) ? $text_field : '',
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
    \Drupal::state()->set('mass_admin_pages.reports_author_block_settings.text_field', $form_state->getValue('text_field')['value']);
    parent::submitForm($form, $form_state);

    $tags[] = 'state:mass_admin_pages.reports_author_block_settings';
    \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_admin_pages.reports_author_block_settings',
    ];
  }

}

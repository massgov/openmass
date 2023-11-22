<?php

namespace Drupal\mass_admin_pages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Contains \Drupal\mass_admin_pages\Form\HelpBlockForm.
 */

/**
 * Configure Site Wide Notification.
 */
class HelpBlockForm extends ConfigFormBase {

  /**
   * Constructs a HelpBlockForm object.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_help_support_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    \Drupal::state()->get('mass_admin_pages.help_block_settings', []);
    $text_field = \Drupal::state()->get('mass_admin_pages.help_block_settings.text_field');
    $link_title = \Drupal::state()->get('mass_admin_pages.help_block_settings.link_title');
    $link_url = \Drupal::state()->get('mass_admin_pages.help_block_settings.link_url');

    $form['text_field'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Main content'),
      '#description' => $this->t('Add content to provide help and support for content authors'),
      '#default_value' => isset($text_field) ? $text_field : '',
    ];

    $form['link_field'] = [
      '#type' => 'item',
      '#title' => $this->t('Link'),
      '#description' => $this->t('Use this field to create a custom link button.'),
      '#wrapper_attributes' => [
        'class' => [
          'help-support-block-link-field-wrapper',
        ],
      ],
    ];

    $form['link_field']['link_subfields'] = [
      'link_title' => [
        '#title' => $this->t('Link title'),
        '#type' => 'textfield',
        '#default_value' => isset($link_title) ? $link_title : '',
        '#required' => FALSE,
      ],

      'link_url' => [
        '#title' => $this->t('URL'),
        '#type' => 'url',
        '#default_value' => isset($link_url) ? $link_url : '',
        '#required' => FALSE,
      ],
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
    \Drupal::state()->set('mass_admin_pages.help_block_settings.text_field', $form_state->getValue('text_field')['value']);
    $link_field = $form_state->getValue(['link_field', 'link_subfields']);
    \Drupal::state()->set('mass_admin_pages.help_block_settings.link_title', $link_field['link_title']);
    \Drupal::state()->set('mass_admin_pages.help_block_settings.link_url', $link_field['link_url']);
    parent::submitForm($form, $form_state);

    $tags[] = 'state:mass_admin_pages.help_block_settings';
    \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_admin_pages.help_block_settings',
    ];
  }

}

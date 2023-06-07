<?php

namespace Drupal\mass_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Header Configuration form class.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_analytics_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $default = $this->config('mass_analytics.settings')->get('looker_studio_url');

    $form['looker_studio_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Looker Studio URL'),
      '#description' => $this->t('Add Looker Studio URL without query string, it will be added in the code.'),
      '#default_value' => $default ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('looker_studio_url');
    $config = $this->config('mass_analytics.settings');
    $config->set('looker_studio_url', $value);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Return the configuration names.
   */
  protected function getEditableConfigNames() {
    return [
      'mass_analytics.settings',
    ];
  }

}

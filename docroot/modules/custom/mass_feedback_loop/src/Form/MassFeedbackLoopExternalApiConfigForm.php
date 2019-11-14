<?php

namespace Drupal\mass_feedback_loop\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MassFeedbackLoopExternalApiConfigForm.
 */
class MassFeedbackLoopExternalApiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_feedback_loop.external_api_config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_feedback_loop_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mass_feedback_loop.external_api_config');
    $form['per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Results Per Page'),
      '#description' => $this->t('Number of results per page to be returned by the external API.'),
      '#default_value' => !empty($config->get('per_page')) ? $config->get('per_page') : 10,
      '#step' => 1,
      '#min' => 10,
      '#max' => 100,
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mass_feedback_loop.external_api_config')
      ->set('per_page', $form_state->getValue('per_page'))
      ->save();
  }

}

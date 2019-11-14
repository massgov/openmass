<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Rinnai Ayla Configuration form class.
 */
class CleanFileManagedConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_utility_clean_file_managed_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mass_utility.clean_file_managed_settings');

    $form['clean_file_managed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clean the file managed table on cron'),
      '#description' => $this->t('Turn on a cron job that removes duplicates from the file_managed table.'),
      '#default_value' => $config->get('clean_file_managed'),
      '#rows' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mass_utility.clean_file_managed_settings');

    $config->set('clean_file_managed', $form_state->getValue('clean_file_managed'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Return the configuration names.
   */
  protected function getEditableConfigNames() {
    return [
      'mass_utility.clean_file_managed_settings',
    ];
  }

}

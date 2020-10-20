<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Header Configuration form class.
 */
class HeaderConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_utility_header_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mass_utility.settings');

    $form['header_mixed_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs for mixed version navigation'),
      '#description' => $this->t("Input the URLs that should be rendered with mixed version of navigation, one per line with leading slash relative URLs (e.g. /personal-income-tax). %front refers to a Homepage.", [
        '%front' => '<front>',
      ]),
      '#default_value' => $config->get('header_mixed_urls'),
      '#rows' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mass_utility.settings');

    // Trim out any blank lines.
    $urls_raw = $form_state->getValue('header_mixed_urls');
    $header_mixed_urls = preg_replace("/(\r?\n){2,}/", "\n", $urls_raw);
    $config->set('header_mixed_urls', $header_mixed_urls);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Return the configuration names.
   */
  protected function getEditableConfigNames() {
    return [
      'mass_utility.settings',
    ];
  }

}

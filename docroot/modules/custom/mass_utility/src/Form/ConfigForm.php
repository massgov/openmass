<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Rinnai Ayla Configuration form class.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_utility_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mass_utility.settings');

    $form['allowed_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed URLs'),
      '#description' => $this->t('Input the URLs that should be allowed for iframe embeds, one per line. If no URLs are specified, all will be allowed.'),
      '#default_value' => $config->get('allowed_urls'),
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
    $urls = $form_state->getValue('allowed_urls');
    $allowed_urls = preg_replace("/(\r?\n){2,}/", "\n", $urls);

    $config->set('allowed_urls', $allowed_urls);
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

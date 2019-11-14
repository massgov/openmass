<?php

namespace Drupal\mass_search_suppression\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures Mass Search Suppression settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_search_suppression_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_search_suppression.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('mass_search_suppression.settings');
    $form['suppression_urls'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#attributes' => ['placeholder' => t('Enter one path per line.')],
      '#title' => $this->t('Pages not showing Search Header'),
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is /user/* for every user page. &lt;front&gt; is the front page."),
      '#default_value' => $config->get('suppression_urls'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $suppression_urls = $form_state->getValue('suppression_urls');
    if (empty($suppression_urls)) {
      return;
    }

    // Check each path entered is a valid one.
    $urls_array = explode(PHP_EOL, $suppression_urls);
    foreach ($urls_array as $url) {
      if (!\Drupal::service('path.validator')->isValid(trim($url))) {
        $form_state->setErrorByName('suppression_urls', $this->t("Please enter one valid path per line."));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mass_search_suppression.settings')
      ->set('suppression_urls', $form_state->getValue('suppression_urls'))
      ->save();

    drupal_set_message($this->t("The configuration options have been saved. If the &lt;front&gt; page setting was changed you would need to clear the cache to see the changes."));
  }

}

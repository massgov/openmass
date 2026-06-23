<?php

namespace Drupal\emoji_validation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class EmojiValidationSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'emoji_validation_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['emoji_validation.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cfg = $this->config('emoji_validation.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable emoji validation'),
      '#default_value' => $cfg->get('enabled'),
    ];

    $form['allowed_codepoints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed code points'),
      '#description' => $this->t('Hex, one per line (e.g., 00B0).'),
      '#default_value' => implode("\n", (array) $cfg->get('allowed_codepoints') ?: []),
    ];

    $form['allowed_ranges'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed ranges'),
      '#description' => $this->t('Hex ranges “START-END”, one per line (e.g., 2200-22FF).'),
      '#default_value' => implode("\n", (array) $cfg->get('allowed_ranges') ?: []),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $norm = static function (string $text): array {
      $lines = preg_split('/\R/u', $text ?? '') ?: [];
      return array_values(array_filter(array_map(static function ($s) {
        $s = strtoupper(trim($s));
        return $s !== '' ? $s : NULL;
      }, $lines)));
    };

    $this->configFactory->getEditable('emoji_validation.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('allowed_codepoints', $norm((string) $form_state->getValue('allowed_codepoints')))
      ->set('allowed_ranges', $norm((string) $form_state->getValue('allowed_ranges')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

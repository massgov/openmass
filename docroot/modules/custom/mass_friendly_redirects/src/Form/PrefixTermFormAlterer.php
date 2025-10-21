<?php

declare(strict_types=1);

namespace Drupal\mass_friendly_redirects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

final class PrefixTermFormAlterer {
  use StringTranslationTrait;

  /**
   * Attach validation to taxonomy term forms for the prefixes vocabulary.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    // Only taxonomy term forms.
    if (($form['#form_id'] ?? '') !== 'taxonomy_term_friendly_url_prefixes_form') {
      return;
    }

    $term = $form_state->getFormObject()->getEntity();
    $vid = $term->bundle();
    if ($vid !== 'friendly_url_prefixes') {
      return;
    }

    // Help text + placeholder to guide editors.
    if (isset($form['name'])) {
      $form['name']['#description'] = $this->t('Enter a URL-friendly, lowercase prefix (letters, numbers, hyphens). Examples: <code>masshealth</code>, <code>dor</code>, <code>ago</code>.');
      $form['name']['widget'][0]['value']['#attributes']['placeholder'] = 'e.g. masshealth';
    }

    // Add our validator.
    $form['#validate'][] = static::class . '::validatePrefixLabel';
  }

  /**
   * Enforce lowercase, URL-friendly labels: ^[a-z0-9][a-z0-9\-]*$.
   */
  public static function validatePrefixLabel(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->bundle() !== 'friendly_url_prefixes') {
      return;
    }

    $values = $form_state->getValue('name');
    $label = is_array($values) ? (string) ($values[0]['value'] ?? '') : (string) $values;
    $label = trim($label);

    if ($label === '') {
      // Let core handle required errors if any.
      return;
    }

    if ($label !== mb_strtolower($label)) {
      $form_state->setErrorByName('name][0][value', t('Prefix must be lowercase.'));
      return;
    }

    if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $label)) {
      $form_state->setErrorByName('name][0][value', t('Only lowercase letters, numbers, and hyphens are allowed. Start with a letter or number.'));
    }
  }

}

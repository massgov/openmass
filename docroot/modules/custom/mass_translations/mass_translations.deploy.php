<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Translations.
 */

use Drupal\taxonomy\TermInterface;

/**
 * Migrate document language fields.
 */
function mass_translations_deploy_language_terms(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $_ENV['MASS_MEDIA_PRESAVE_BYPASS'] = TRUE;

  $english_target_id = 8876;

  $query = \Drupal::entityQuery('media')
    ->accessCheck(FALSE)
    ->condition('bundle', 'document')
    ->condition('field_language.target_id', $english_target_id, '!=')
    ->condition('field_upload_file.target_id', '', '!=');

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 100;

  $mids = $query->condition('mid', $sandbox['current'], '>')
    ->sort('mid')
    ->range(0, $batch_size)
    ->accessCheck(FALSE)
    ->execute();

  $memory_cache = \Drupal::service('entity.memory_cache');

  $media_storage = \Drupal::entityTypeManager()->getStorage('media');

  $media_items = $media_storage->loadMultiple($mids);

  foreach ($media_items as $media_item) {
    $sandbox['current'] = $media_item->id();

    $langcode_map = [
      'Spanish' => 'es',
      'Portuguese' => 'pt-pt',
      'Chinese' => 'zh-hans',
      'Vietnamese' => 'vi',
      'Haitian Creole' => 'ht',
      'Russian' => 'ru',
      'Russian (Russia)' => 'ru',
      'Khmer' => 'km',
      'Arabic' => 'ar',
      'French' => 'fr',
      'Cape Verdean Creole' => 'cv',
      'Korean' => 'ko',
      'Cape Verdean' => 'cv',
      'Italian' => 'it',
      'Creole' => 'ht',
      'Afrikaans' => 'af',
      'Lao' => 'lo',
      'Ukranian' => 'uk',
      'Portuguese (Brazil)' => 'pt-br',
    ];

    $term_entity = $media_item->get('field_language')->entity;
    if ($term_entity instanceof TermInterface) {
      $field_language_value = $term_entity->label();

      if (in_array($field_language_value, array_keys($langcode_map))) {
        $media_item->set('langcode', $langcode_map[$field_language_value]);
        $media_item->save();
      }
    }

    $sandbox['progress']++;
  }

  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All document language fields migrated.');
  }
}

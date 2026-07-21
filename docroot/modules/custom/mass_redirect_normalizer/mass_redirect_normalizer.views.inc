<?php

/**
 * @file
 * Views data for mass_redirect_normalizer.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function mass_redirect_normalizer_views_data(): array {
  $data = [];
  $data['mass_redirect_normalizer_change_log']['table'] = [
    'group' => t('Redirect link normalization'),
    'base' => [
      'field' => 'id',
      'title' => t('Redirect link normalization log'),
      'help' => t('Redirect link normalization change and failure log entries.'),
      'weight' => -10,
    ],
  ];

  $fields = [
    'id' => t('ID'),
    'changed_at' => t('Changed'),
    'status' => t('Status'),
    'source' => t('Source'),
    'entity_type' => t('Entity type'),
    'entity_id' => t('Entity ID'),
    'bundle' => t('Bundle'),
    'field_name' => t('Field'),
    'delta' => t('Delta'),
    'kind' => t('Kind'),
    'before_value' => t('Before'),
    'after_value' => t('After'),
    'error_message' => t('Error'),
  ];

  foreach ($fields as $field => $title) {
    $data['mass_redirect_normalizer_change_log'][$field] = [
      'title' => $title,
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
  }

  $data['mass_redirect_normalizer_change_log']['changed_at']['field'] = [
    'id' => 'date',
    'date_format' => 'custom',
    'custom_date_format' => 'Y-m-d H:i:s',
  ];
  $data['mass_redirect_normalizer_change_log']['changed_at']['sort']['id'] = 'date';

  return $data;
}

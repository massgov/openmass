<?php

/**
 * @file
 * Views integration for mass_entity_usage.
 */

/**
 * Implements hook_views_data().
 */
function mass_entity_usage_views_data() {
  $data['entity_usage']['table']['group'] = t('Entity Usage');
  $data['entity_usage']['table']['base'] = [
    // entity_usage uses a composite primary key, so we anchor the base table
    // to a stable leading key so it can be selected in the Views UI.
    'field' => 'target_id',
    'title' => t('Entity usage'),
    'help' => t('Records of source entities referencing target entities.'),
  ];

  $data['entity_usage']['target_id'] = [
    'title' => t('Target entity ID'),
    'help' => t('The numeric ID of the referenced target entity.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['entity_usage']['target_id_string'] = [
    'title' => t('Target entity ID (string)'),
    'help' => t('The string ID of the referenced target entity.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['target_type'] = [
    'title' => t('Target entity type'),
    'help' => t('The entity type of the referenced target entity.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['source_id'] = [
    'title' => t('Source entity ID'),
    'help' => t('The numeric ID of the entity containing the reference.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['entity_usage']['source_id_string'] = [
    'title' => t('Source entity ID (string)'),
    'help' => t('The string ID of the entity containing the reference.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['source_type'] = [
    'title' => t('Source entity type'),
    'help' => t('The entity type of the source entity.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['source_langcode'] = [
    'title' => t('Source language'),
    'help' => t('The language code recorded for the source entity.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['source_vid'] = [
    'title' => t('Source revision ID'),
    'help' => t('The revision ID recorded for the source entity.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['entity_usage']['method'] = [
    'title' => t('Tracking method'),
    'help' => t('The entity_usage tracking plugin or method that found the reference.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['entity_usage']['field_name'] = [
    'title' => t('Field name'),
    'help' => t('The source field where the reference was found.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function mass_entity_usage_views_data_alter(array &$data) {
  _mass_entity_usage_add_entity_relationship($data, 'node', 'node_field_data', 'nid', 'Content');
  _mass_entity_usage_add_entity_relationship($data, 'media', 'media_field_data', 'mid', 'Media');
  _mass_entity_usage_add_entity_relationship($data, 'paragraph', 'paragraphs_item_field_data', 'id', 'Paragraph');

  // Views UI adds related bundle filters before the relationship is chosen.
  // Use a bundle handler that can infer the entity type from its own table so
  // the edit form does not crash in that temporary state.
  $data['node_field_data']['type']['filter']['id'] = 'mass_entity_usage_bundle';
  $data['media_field_data']['bundle']['filter']['id'] = 'mass_entity_usage_bundle';
}

/**
 * Adds source and target relationships for an entity_usage entity type.
 *
 * This stays intentionally small and site-specific: entity_usage records on
 * this site primarily point at nodes, media, and paragraphs.
 *
 * @param array $data
 *   Views data.
 * @param string $entity_type_id
 *   The entity type machine name stored in entity_usage.
 * @param string $views_table
 *   The Views table to relate to.
 * @param string $id_key
 *   The entity ID field on the related table.
 * @param string $label
 *   The relationship label to show in the Views UI.
 */
function _mass_entity_usage_add_entity_relationship(array &$data, $entity_type_id, $views_table, $id_key, $label) {
  $data['entity_usage']["source_{$entity_type_id}"] = [
    'title' => t('Source @entity_type', ['@entity_type' => $label]),
    'help' => t('The source @entity_type that contains the reference.', ['@entity_type' => strtolower($label)]),
    'real field' => 'source_id',
    'relationship' => [
      'id' => 'standard',
      'base' => $views_table,
      'base field' => $id_key,
      'relationship field' => 'source_id',
      'label' => t('Source @entity_type', ['@entity_type' => $label]),
      'extra' => [
        [
          'table' => 'entity_usage',
          'field' => 'source_type',
          'value' => $entity_type_id,
        ],
      ],
    ],
  ];

  $data['entity_usage']["target_{$entity_type_id}"] = [
    'title' => t('Target @entity_type', ['@entity_type' => $label]),
    'help' => t('The target @entity_type being referenced.', ['@entity_type' => strtolower($label)]),
    'real field' => 'target_id',
    'relationship' => [
      'id' => 'standard',
      'base' => $views_table,
      'base field' => $id_key,
      'relationship field' => 'target_id',
      'label' => t('Target @entity_type', ['@entity_type' => $label]),
      'extra' => [
        [
          'table' => 'entity_usage',
          'field' => 'target_type',
          'value' => $entity_type_id,
        ],
      ],
    ],
  ];
}

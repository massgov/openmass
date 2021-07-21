<?php

/**
 * @file
 * Mass Dashboard views hooks.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_views_data_alter().
 *
 * Adds a better content bundle filter.
 */
function mass_views_views_data_alter(array &$data) {
  // Adds a better content type filter.
  $data['node_field_data']['node_user_permitted_type_filter'] = [
    'title' => t('Mass.gov user permitted content type filter'),
    'filter' => [
      'title' => t('User permitted content type'),
      'help' => t('Filter nodes by their type, options limited by user add/edit permissions.'),
      'field' => 'type',
      'id' => 'mass_views_user_permitted_bundle',
    ],
  ];

  // Adds an org filter for nodes.
  $data['node_field_data']['node_org_filter'] = [
    'title' => 'Organization Filter',
    'filter' => [
      'title' => t('Organization Filter'),
      'help' => t('Filter nodes by their organization reference'),
      'field' => 'nid',
      'id' => 'mass_views_node_org_filter',
    ],
  ];

  // Adds an org parent filter for nodes.
  $data['node_field_data']['node_parent_org_filter'] = [
    'title' => 'Organization Parent Filter',
    'filter' => [
      'title' => t('Organization Parent Filter'),
      'help' => t('Filter nodes by their organization parent reference'),
      'field' => 'nid',
      'id' => 'mass_views_node_org_parent_filter',
    ],
  ];

  // Adds an org filter for nodes.
  $data['node_field_data']['node_billing_org_filter'] = [
    'title' => 'Billing Organization Filter',
    'filter' => [
      'title' => t('Billing Organization Filter'),
      'help' => t('Filter nodes by their Billing organization reference'),
      'field' => 'nid',
      'id' => 'mass_views_node_billing_org_filter',
    ],
  ];

  // Adds an org filter for media.
  $data['media_field_data']['media_org_filter'] = [
    'title' => 'Organization Filter',
    'filter' => [
      'title' => t('Organization Filter'),
      'help' => t('Filter media by their organization reference'),
      'field' => 'nid',
      'id' => 'mass_views_media_org_filter',
    ],
  ];
}

/**
 * Implements hook_field_views_data_alter().
 *
 * Adds relationships based on link fields.
 * Code based on views.views.inc/core_field_views_data().
 */
function mass_views_field_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
  // The code below only deals with the Link field type.
  if ($field_storage->getType() != 'link') {
    return $data;
  }
  $field_name = $field_storage->getName();
  $entity_type_manager = \Drupal::entityTypeManager();

  $entity_type_id = $field_storage->getTargetEntityTypeId();

  $entity_type = $entity_type_manager->getDefinition($entity_type_id);

  /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
  $table_mapping = $entity_type_manager->getStorage($entity_type_id)->getTableMapping();

  // In the model code, these are retrieved in a loop.
  // The link field may from any entity, but this only support links TO nodes.
  $target_entity_type_id = 'node';
  $target_entity_type = $entity_type_manager->getDefinition($target_entity_type_id);
  $target_base_table = $target_entity_type->getDataTable() ?: $target_entity_type->getBaseTable();
  $target_label = 'Internal Node Page';

  foreach ($data as $table_name => $table_data) {
    // Create a relationship from the linking node to the linked node.
    $pseudo_field_name = 'link_from__' . $entity_type_id . '__' . $field_name;
    $args = [
      '@label' => $target_label,
      '@field_name' => $field_name,
    ];
    $data[$table_name][$pseudo_field_name]['relationship'] = [
      'title' => t('@label linked from @field_name', $args),
      'label' => t('@label from @field_name', $args),
      'group' => $entity_type->getLabel(),
      'help' => t('Appears in: @bundles.', ['@bundles' => implode(', ', $field_storage->getBundles())]),
      'id' => 'standard',
      'base' => $target_base_table,
      'entity type' => $target_entity_type_id,
      'base field' => $target_entity_type->getKey('id'),
      'relationship field' => $field_name . '_uri',
      'join_id' => 'link_join',
    ];

    // Provide a reverse relationship.
    $args['@entity'] = $entity_type->getLabel();
    $args['@label'] = $target_entity_type->getSingularLabel();
    $pseudo_field_name = 'linked_by__' . $entity_type_id . '__' . $field_name;
    $data[$target_base_table][$pseudo_field_name]['relationship'] = [
      'title' => t('@entity linking to node using @field_name', $args),
      'label' => t('@entity linking to node using @field_name', $args),
      'group' => $target_entity_type->getLabel(),
      'help' => t('Relate each @entity with a @field_name link to the node.', $args),
      'id' => 'link_reverse',
      'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
      'entity_type' => $entity_type_id,
      'base field' => $entity_type->getKey('id'),
      'field_name' => $field_name,
      'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
      'field field' => $field_name . '_uri',
      'join_extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ];
  }
  return $data;
}

<?php

/**
 * @file
 * Mass Dashboard views hooks.
 */

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_views_data().
 */
function mass_views_views_data() {

  $data['node']['source_id_target_count'] = [
    'title' => t('Target Count for Source ID'),
    'help' => t('Displays the number of target IDs associated with the source ID in entity_usage table.'),
    'field' => [
      'id' => 'source_id_target_count',
      'click sortable' => TRUE,
      'help' => t('Displays the number of target IDs associated with the source ID in entity_usage table.'),
    ],
  ];

  $data['views']['cl_automatic_lists'] = [
    'title' => t('Automatic lists (Curated list)'),
    'help' => t('Number of Automatic lists in the Curated list'),
    'field' => [
      'id' => 'cl_automatic_lists',
    ],
  ];
  $data['views']['cl_manual_lists'] = [
    'title' => t('Manual lists (Curated list)'),
    'help' => t('Number of Manual lists in the Curated list'),
    'field' => [
      'id' => 'cl_manual_lists',
    ],
  ];
  $data['views']['cl_manual_lists_links'] = [
    'title' => t('Manual lists links (Curated list)'),
    'help' => t('Number of Manual lists links in the Curated list'),
    'field' => [
      'id' => 'cl_manual_lists_links',
    ],
  ];
  $data['views']['cl_manual_lists_downloads'] = [
    'title' => t('Manual lists downloads (Curated list)'),
    'help' => t('Number of Manual lists downloads in the Curated list'),
    'field' => [
      'id' => 'cl_manual_lists_downloads',
    ],
  ];
  return $data;
}

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

  // Adds an org count filter for nodes.
  $data['node_field_data']['node_org_count_filter'] = [
    'title' => 'Organization Count Filter',
    'filter' => [
      'title' => t('Organization Count Filter'),
      'help' => t('Filter nodes by their organization reference count'),
      'field' => 'nid',
      'id' => 'mass_views_node_org_count_filter',
    ],
  ];

  // Adds parent page autocomplete filter for nodes.
  $data['node_field_data']['node_parent_filter'] = [
    'title' => 'Parent Page Filter',
    'filter' => [
      'title' => t('Parent Page Filter'),
      'help' => t('Filter nodes by their parent page.'),
      'field' => 'nid',
      'id' => 'mass_views_node_parent_filter',
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

  // Adds a linked from Parent field.
  $data['node_field_data']['linked_from_parent'] = [
    'title' => t('Linked from parent'),
    'field' => [
      'title' => t('Linked from Parent'),
      'id' => 'linked_from_parent',
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

  // Adds a social media count field to the organization pages content report.
  $data['paragraphs_item_field_data']['org_social_media_count'] = [
    'title' => t('Organization social media count'),
    'field' => [
      'title' => t('Organization social media count'),
      'help' => t('Number of socia media references by an organization.'),
      'id' => 'org_social_media_count',
    ],
  ];

  // Adds a featured messages count field to the organization pages content
  // report.
  $data['paragraphs_item_field_data']['org_featured_messages_count'] = [
    'title' => t('Organization featured messages count'),
    'field' => [
      'title' => t('Organization featured messages count'),
      'help' => t('Number of featured message references by an organization.'),
      'id' => 'org_featured_messages_count',
    ],
  ];

  // Adds a featured item mosaic count field to the organization pages content
  // report.
  $data['paragraphs_item_field_data']['org_featured_item_mosaic_count'] = [
    'title' => t('Organization featured item mosaic count'),
    'field' => [
      'title' => t('Organization featured item mosaic count'),
      'help' => t('Number of featured item mosaic references by an organization.'),
      'id' => 'org_featured_item_mosaic_count',
    ],
  ];

  // Adds a maps count field to the organization pages content report.
  $data['paragraphs_item_field_data']['org_maps_count'] = [
    'title' => t('Organization maps count'),
    'field' => [
      'title' => t('Organization maps count'),
      'help' => t('Number of map references by an organization.'),
      'id' => 'org_maps_count',
    ],
  ];

  // Adds a wwyltd count field to the organization pages content report.
  $data['paragraphs_item_field_data']['org_wwyltd_count'] = [
    'title' => t('Organization "What would you like to do?" count'),
    'field' => [
      'title' => t('Organization "What would you like to do?" count'),
      'help' => t('Number of "What would you like to do?" references by an organization.'),
      'id' => 'org_wwyltd_count',
    ],
  ];

  // Adds a news count field to the organization pages content report.
  $data['paragraphs_item_field_data']['org_news_count'] = [
    'title' => t('Organization news count'),
    'field' => [
      'title' => t('Organization news count'),
      'help' => t('Number of news references by an organization.'),
      'id' => 'org_news_count',
    ],
  ];

  // Adds a featured topics count field to the organization pages content
  // report.
  $data['paragraphs_item_field_data']['org_featured_topics_count'] = [
    'title' => t('Organization featured topics count'),
    'field' => [
      'title' => t('Organization featured topics count'),
      'help' => t('Number of featured topic references by an organization.'),
      'id' => 'org_featured_topics_count',
    ],
  ];

  // Adds an organization grid count field to the organization pages content
  // report.
  $data['paragraphs_item_field_data']['org_grid_count'] = [
    'title' => t('Organization grid count'),
    'field' => [
      'title' => t('Organization grid count'),
      'help' => t('Number of grid references by an organization.'),
      'id' => 'org_grid_count',
    ],
  ];

  // Add a custom field for displaying the redirect from the redirect table.
  $data['media_field_data']['redirect'] = [
    'title' => t('Redirect Source'),
    'help' => t('Displays the redirect Source that is pointing to this media item.'),
    'field' => [
      'id' => 'media_redirect_field',
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

<?php

/**
 * @file
 * Mass Content API views hooks.
 */

/**
 * Implements hook_views_data().
 */
function mass_content_api_views_data() {
  // Define the return array.
  $data = [];
  // The outermost keys of $data are Views table names.
  $data['descendant_relations'] = [];
  // The value corresponding to key 'table' gives properties of the table.
  $data['descendant_relations']['table'] = [];
  // Within 'table', the value of 'group' (translated string) is used as a
  // prefix in Views UI for this table's fields, filters, etc. When adding
  // a field, filter, etc. you can also filter by the group.
  $data['descendant_relations']['table']['group'] = t('Mass Content API Descendant Relations');
  // Within 'table', the value of 'provider' is the module that provides schema
  // or the entity type that causes the table to exist. Setting this ensures
  // that views have the correct dependencies. This is automatically set to the
  // module that implements hook_views_data().
  $data['descendant_relations']['table']['provider'] = 'mass_content_api';
  // Some tables are "base" tables, meaning that they can be the base tables
  // for views. Non-base tables can only be brought in via relationships in
  // views based on other tables. To define a table to be a base table, add
  // key 'base' to the 'table' array:
  $data['descendant_relations']['table']['base'] = [
    // Identifier (primary) field in this table for Views. This table doesn't
    // have a primary key, so the field value here is primarily used in the
    // views filter plugin.
    'field' => 'destination_id',
    // Label in the UI.
    'title' => t('Descendant Relations'),
    // Longer description in the UI. Required.
    'help' => t('Contains records of links from one node to another.'),
  ];
  // Return the data array.
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function mass_content_api_views_data_alter(array &$data) {
  // Adds an descendant relations filter for nodes.
  $data['node_field_data']['node_descendant_relations_filter'] = [
    'title' => 'Descendant Relationship Filter',
    'filter' => [
      'title' => t('Descendant Relationship Filter'),
      'help' => t('Filter nodes by their descendant relationships'),
      'field' => 'nid',
      'id' => 'mass_content_api_descendant_relations_filter',
    ],
  ];
}

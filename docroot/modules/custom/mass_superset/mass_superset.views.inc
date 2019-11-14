<?php

/**
 * @file
 * Provide views data for mass_superset.module.
 *
 * @ingroup views_module_handlers
 */

/**
 * Implements hook_views_data().
 */
function mass_superset_views_data() {
  $data = [];
  $data['mass_superset_data'] = [];
  $data['mass_superset_data']['table'] = [];
  $data['mass_superset_data']['table']['group'] = t('Superset data');

  $data['mass_superset_data']['table']['join'] = [
    'node_field_data' => [
      'type' => 'LEFT',
      'left_field' => 'nid',
      'field' => 'nid',
    ],
  ];

  $data['mass_superset_data']['nid'] = [
    'title' => t('Node ID'),
    'help' => t('The nid of the node entity.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['mass_superset_data']['pageviews'] = [
    'title' => t('Page views'),
    'help' => t('The pageviews for the node entity.'),
    'field' => [
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['score'] = [
    'title' => t('Score'),
    'help' => t('The overall score for the node entity.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Score'),
      'help' => t('Specify the range of scores to show.'),
      'field' => 'score',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['last_updated'] = [
    'title' => t('Last Updated'),
    'help' => t('The UNIX time stamp representing when the node was last updated.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['snooze'] = [];
  $data['snooze']['table'] = [];
  $data['snooze']['table']['group'] = t('Snooze data');

  $data['snooze']['table']['join'] = [
    'node_field_data' => [
      'type' => 'LEFT',
      'left_field' => 'nid',
      'field' => 'entity_id',
    ],
  ];

  $data['snooze']['last_updated'] = [
    'title' => t('Date Snoozed'),
    'help' => t('The UNIX time stamp representing when the node was snoozed.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'title' => t('Snoozed'),
      'help' => t('Show snoozed nodes.'),
      'field' => 'last_updated',
      'id' => 'date',
    ],
  ];

  return $data;
}

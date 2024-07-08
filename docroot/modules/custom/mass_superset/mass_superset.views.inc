<?php

/**
 * @file
 * Provide views data for mass_superset.module.
 *
 * @ingroup views_module_handlers
 */

declare(strict_types=1);

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
    'filter' => [
      'title' => t('Page views'),
      'help' => t('Limit pages to those with a certain number of pageviews.'),
      'field' => 'pageviews',
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

  $data['mass_superset_data']['nos_per_1000'] = [
    'title' => t('Nos per 1000'),
    'help' => t('The number of No responses per 1000.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Nos per 1000'),
      'help' => t('Limit pages to those with a certain number of Nos per 1000.'),
      'field' => 'nos_per_1000',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['eject_rate'] = [
    'title' => t('Eject Rate'),
    'help' => t('The eject rate.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Eject Rate'),
      'help' => t('Limit pages to those with a certain eject rate.'),
      'field' => 'eject_rate',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['broken_links'] = [
    'title' => t('Broken Links'),
    'help' => t('The number of broken links.'),
    'field' => [
      'id' => 'numeric',
      'float' => FALSE,
    ],
    'filter' => [
      'title' => t('Broken Links'),
      'help' => t('Limit pages to those with a certain rate of broken links.'),
      'field' => 'broken_links',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['grade_level'] = [
    'title' => t('Reading Level'),
    'help' => t('The grade reading level.'),
    'field' => [
      'id' => 'numeric',
      'float' => FALSE,
    ],
    'filter' => [
      'title' => t('Reading Level'),
      'help' => t('Limit pages to those with a certain reading level.'),
      'field' => 'grade_level',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['total_no'] = [
    'title' => t('Total No'),
    'help' => t('The total number of no responses by month.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Total No'),
      'help' => t('Limit pages to those with a certain number of no responses by month.'),
      'field' => 'total_no',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['total_yes'] = [
    'title' => t('Total Yes'),
    'help' => t('The total number of yes responses by month.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Total Yes'),
      'help' => t('Limit pages to those with a certain number of yes responses by month.'),
      'field' => 'total_yes',
      'id' => 'numeric',
    ],
  ];

  $data['mass_superset_data']['total_feedback'] = [
    'title' => t('Total Feedback'),
    'help' => t('The total feedback for month.'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Total Feedback'),
      'help' => t('Limit pages to those with a certain number of feedback by month.'),
      'field' => 'total_feedback',
      'id' => 'numeric',
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

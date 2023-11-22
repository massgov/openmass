<?php

/**
 * @file
 * Provide views data for mass_bigquery.module.
 *
 * @ingroup views_module_handlers
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function mass_bigquery_views_data() {
  $data = [];
  $data['mass_bigquery_data'] = [];
  $data['mass_bigquery_data']['table'] = [];
  $data['mass_bigquery_data']['table']['group'] = t('BigQuery data');

  $data['mass_bigquery_data']['table']['join'] = [
    'node_field_data' => [
      'type' => 'LEFT',
      'left_field' => 'nid',
      'field' => 'nid',
    ],
  ];

  $data['mass_bigquery_data']['nid'] = [
    'title' => t('Node ID'),
    'help' => t('The nid of the node entity.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['mass_bigquery_data']['pageviews'] = [
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

  $data['mass_bigquery_data']['nos_per_1000_cleaned'] = [
    'title' => t('Nos per 1000 cleaned'),
    'help' => t('Nos per 1000 cleaned'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'title' => t('Nos per 1000 cleaned'),
      'help' => t('Specify the range of values to show.'),
      'field' => 'nos_per_1000_cleaned',
      'id' => 'numeric',
    ],
  ];

  $data['mass_bigquery_data']['last_updated'] = [
    'title' => t('Last Updated'),
    'help' => t('The UNIX time stamp representing when the node was last updated.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['mass_bigquery_data']['nos_per_1000'] = [
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

  $data['mass_bigquery_data']['eject_rate'] = [
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

  $data['mass_bigquery_data']['broken_links'] = [
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

  $data['mass_bigquery_data']['grade_level'] = [
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

  $data['mass_bigquery_data']['total_no'] = [
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

  $data['mass_bigquery_data']['total_yes'] = [
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

  $data['mass_bigquery_data']['total_feedback'] = [
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

  return $data;
}

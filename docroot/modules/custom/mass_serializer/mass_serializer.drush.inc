<?php

/**
 * @file
 * Drush commands for mass_serializer.
 */

/**
 * Implements hook_drush_command().
 */
function mass_serializer_drush_command() {
  // Rebuild cache for a data.json feed.
  $items['mass-serializer-cache'] = [
    'aliases' => ['mserc'],
    'description' => '.',
    'callback' => 'mass_serializer_file_cache_endpoint',
    'arguments' => [
      'machine_name' => 'Machine name for the endpoint to be cached, currently only works for "documents_by_filter".',
      'term_id' => 'Term id for the odsm endpoint to be cached, e.g. 3111.',
    ],
  ];

  // Rebuild cache for a data.json feed.
  $items['mass-serializer-render-partial'] = [
    'aliases' => ['mserp'],
    'description' => '.',
    'callback' => 'mass_serializer_file_render_partial',
    'arguments' => [
      'machine_name' => 'Machine name for the endpoint to be cached, currently only works for "documents_by_filter".',
      'display' => 'Machine name for the view display to render, e.g. rest_export_1',
      'filename' => 'Temp file name stream wrapper to use.',
      'term_id' => 'Term id for the odsm endpoint to be cached, e.g. 3111.',
      'offset' => 'Number of records to skip.',
    ],
  ];

  // Calls mass-serializer-cache command for all organizations.
  $items['mass-serializer-cache-all'] = [
    'aliases' => ['mserca'],
    'description' => '.',
    'callback' => 'mass_serializer_file_cache_endpoint_all',
    'arguments' => [
      'machine_name' => 'Machine name for the endpoint to be cached, currently only works for "documents_by_filter".',
      'limit' => '(Optional) Number of Terms to be processed during batch, e.g. 10. Mainly for testing purposes.',
    ],
  ];

  // Calls mass-serializer-merge-file for all organizations.
  $items['mass-serializer-merge-file'] = [
    'aliases' => ['msmf'],
    'description' => 'Internal drush command only to be used by mserc',
    'callback' => 'mass_serialize_merge_file',
    'arguments' => [
      'cachename' => 'String for the filename.',
      'filenames' => 'All files created for org with the given offset.',
    ],
  ];

  return $items;
}

/**
 * Wrapper function so drush can cache partial files.
 *
 * @param string $machine_name
 *   Name of the endpoint you are saving.
 * @param string $display
 *   Display ID of the view to use.
 * @param string $filename
 *   Name of the temp file you are saving.
 * @param int $term_id
 *   Arguments to supply to the view.
 * @param int $offset
 *   If using multiple pages, supply an offset to differentiate args.
 */
function mass_serializer_file_render_partial($machine_name, $display, $filename, $term_id, $offset) {
  $cache = \Drupal::service('mass_serializer.cache_endpoint');
  $cache->renderPartial($machine_name, $display, $filename, [$term_id], FALSE, $offset);
}

/**
 * Wrapper function so drush can call this service.
 *
 * @param string $machine_name
 *   The harvest source machine name.
 * @param int $term_id
 *   The organization term id.
 */
function mass_serializer_file_cache_endpoint($machine_name, $term_id) {
  $cache = \Drupal::service('mass_serializer.cache_endpoint');
  $cache->cacheSave($machine_name, [$term_id]);
}

/**
 * Wrapper function so drush can call this service.
 *
 * @param string $machine_name
 *   The harvest source machine name.
 * @param int $limit
 *   The number of organizations to process.
 */
function mass_serializer_file_cache_endpoint_all($machine_name, $limit = 0) {
  // Avoid "the Mysql server went away".
  $cache = \Drupal::service('mass_serializer.cache_endpoint');
  $cache->setDbTimeout();

  $query = Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', "user_organization");
  $tids = $query->execute();

  drush_print('Total number of Organizations to process: ' . ($limit != 0 ? $limit : count($tids)));

  $processed = 0;
  foreach ($tids as $tid) {
    drush_print('Calling mserc drush command with organization id: ' . $tid);
    // Drush_invoke_process because invokes a command in a new process.
    drush_invoke_process('@self', 'mserc', [
      'machine_name' => $machine_name,
      'term_id' => $tid,
    ]);
    $processed++;

    // If limit is reached then stop processing organizations.
    // This is mainly for testing purposes (you can specify only 2 items
    // for example to see if the data is exported as expected).
    if ($limit != 0 && $limit == $processed) {
      break;
    }
  }

  drush_print('Finished generating Json files.');
}

/**
 * Merges rendered json into one master json file.
 *
 * @param string $cachename
 *   Cache name for the file.
 * @param string $filenames
 *   All files created for org with the given offset.
 */
function mass_serialize_merge_file($cachename, $filenames) {
  $cache = \Drupal::service('mass_serializer.cache_endpoint');
  $cache->mergeFiles($cachename, explode(' ', $filenames));
}

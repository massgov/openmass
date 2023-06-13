<?php
namespace Google\Cloud\Samples\BigQuery;

use Google\Cloud\BigQuery\BigQueryClient;

/**
 * Run query as job.
 *
 * @param string $projectId The project Id of your Google Cloud Project.
 * @param string $query Eg: 'SELECT id, view_count FROM
 *                          `bigquery-public-data.stackoverflow.posts_questions`';
 */
function run_query_as_job(string $projectId, string $query): void
{
    $keyFile = json_decode(file_get_contents('/var/www/html/docroot/winged-verbena-220618-76e8f298ff6a.json'), true);

    $bigQuery = new BigQueryClient([
      'projectId' => $projectId,
      'keyFile' => $keyFile,
    ]);

    // Set job configs
    $jobConfig = $bigQuery->query($query);
    $jobConfig->useQueryCache(false);
    // $jobConfig->dryRun(true);

    // Extract query results
    //$queryJob = $bigQuery->startJob($jobConfig);
    //$info = $queryJob->info();
    //var_dump($info);
    $queryResults = $bigQuery->runQuery($jobConfig);

    $i = 0;
    foreach ($queryResults as $row) {
        printf('--- Row %s ---' . PHP_EOL, ++$i);
        foreach ($row as $column => $value) {
            //printf('%s: %s' . PHP_EOL, $column, json_encode($value));
            echo $column . "\n";
        }
    }
    // printf('Found %s row(s)' . PHP_EOL, $i);
/*
    echo "projectId: $projectId\n";
    $bigQuery = new BigQueryClient([
      'projectId' => $projectId,
    ]);
    $jobConfig = $bigQuery->query($query);
*/
    /*
    $queryResults = $bigQuery->runQuery($jobConfig);

    $i = 0;
    foreach ($queryResults as $row) {
        printf('--- Row %s ---' . PHP_EOL, ++$i);
        foreach ($row as $column => $value) {
            printf('%s: %s' . PHP_EOL, $column, json_encode($value));
        }
    }
    printf('Found %s row(s)' . PHP_EOL, $i);
     */
}

run_query_as_job('winged-verbena-220618', 'SELECT * FROM `winged-verbena-220618.MassgovGA4_testing.aggregated_node_analytics` ORDER BY totalPageViews desc LIMIT 1000');

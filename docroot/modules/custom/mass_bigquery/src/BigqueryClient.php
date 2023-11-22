<?php

namespace Drupal\mass_bigquery;

use Google\Cloud\BigQuery\BigQueryClient as GoogleBigQueryClient;

/**
 * A small wrapper around Google's BigQueryClient.
 */
class BigqueryClient {
  private $client;

  /**
   * Setup the client to connect to.
   *
   * @return \Google\Cloud\BigQuery\BigQueryClient
   *   A client that allows us to query bigquery.
   */
  private function getClient() {
    if (!$this->client) {
      $this->client = new GoogleBigQueryClient();
    }
    return $this->client;
  }

  /**
   * Run a query against BigQuery.
   *
   * This method may throw an exception if it is unable to reach Superset,
   * or if the query generates an error.
   *
   * @param string $query
   *   The query to send.
   *
   * @return \Google\Cloud\BigQuery\QueryResults
   *   An iterator of query results.
   *
   * @throws \Exception
   *   An error from GuzzleHttp.
   */
  public function runQuery(string $query) {
    $client = $this->getClient();
    $jobConfig = $client->query($query);
    $jobConfig->useQueryCache(FALSE);
    $queryResults = $client->runQuery($jobConfig);

    return $queryResults;
  }

}

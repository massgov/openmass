<?php

namespace Drupal\mass_superset;

use GuzzleHttp\Client;

/**
 * A PHP Client for executing queries via Superset SQL Lab and fetching results.
 *
 * This allows the backing database to be queried without being directly exposed to the internet.
 */
class SupersetDatabaseClient {
  private $client;
  private $csrfToken;

  /**
   * Setup the client to connect to.
   *
   * @param array $options
   *   An array of options for defining the client.
   *
   * @return \GuzzleHttp\Client
   *   A client that allows us to send requests.
   */
  private function getClient(array $options) {
    if (!$this->client) {
      $this->client = new Client([
        'base_uri' => $options['base_uri'],
        'cookies' => TRUE,
      ]);
      $response = $this->client->get('/api/v1/security/csrf_token/');
      $response_object = json_decode($response->getBody()->getContents());
      $this->csrfToken = $response_object->result;

      if (!empty($options['username']) && !empty($options['password'])) {
        // Perform login.
        $this->client->post('/login/', [
          'allow_redirects' => FALSE,
          'form_params' => [
            'username' => $options['username'],
            'password' => $options['password'],
            'csrf_token' => $this->csrfToken,
          ],
        ]);
      }
    }
    return $this->client;
  }

  /**
   * Execute an SQL query on the Superset database.
   *
   * This method may throw an exception if it is unable to reach Superset,
   * or if the query generates an error.
   *
   * @param string $query
   *   The query to send.
   * @param array $options
   *   The options to pass on to the client.
   *
   * @return array
   *   The data that is returned from Superset.
   *
   * @throws \Exception
   *   An error from GuzzleHttp.
   */
  public function runQuery(string $query, array $options) {
    $client = $this->getClient($options);

    $response = $client->post('/api/v1/sqllab/execute', [
      'headers' => [
        'X-CSRFToken' => $this->csrfToken,
      ],
      'json' => [
        // Client ID must be a random string.
        'client_id' => bin2hex(random_bytes(5)),
        'json' => TRUE,
        'database_id' => $options['database_id'],
        'schema' => $options['schema'],
        'runAsync' => FALSE,
        'sql' => $query,
      ],
    ]);
    return json_decode($response->getBody()->getContents(), TRUE);
  }

}

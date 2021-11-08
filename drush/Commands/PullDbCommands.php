<?php

namespace Drush\Commands;

class PullDbCommands extends DrushCommands {

  /**
   * Fetch latest DB snapshot from CircleCI.
   *
   * @command ma:pulldb
   *
   * @param array $options The options list.
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @option $type Recognized values: super, regular.
   * @option ci-branch The branch that CircleCI should check out at start.
   *
   * @aliases ma-pulldb
   * @validate-circleci-token
   *
   */
  public function pulldb(array $options = ['ci-branch' => 'develop', 'type' => 'super'])
  {
    $type = $options['type'];

    $cmd = 'rm -rf .ddev/db_snapshots/' . $type;
    $this->processManager()->shell($cmd)->mustRun();

    $stack = $this->getStack();
    $client = new \GuzzleHttp\Client(['handler' => $stack]);

    $this->logger()->notice('Start get recent builds.');
    $options = [
      'auth' => DeployCommands::getTokenCircle(),
      'headers' => ['Accept' => 'text/plain'],
    ];
    $params = [
      'limit' => 100,
      'filter' => 'successful',
    ];
    // @todo Change to develop before merge.
    $branch = 'new-db-image';
    $url = DeployCommands::CIRCLE_URI_BASE . '/v1.1/project/' . DeployCommands::SLUG . "/tree/$branch?" . http_build_query($params);
    $response = $client->request('GET', $url, $options);
    DeployCommands::exceptionIfFailed($response);
    $items = json_decode((string)$response->getBody());
    foreach ($items as $item) {
      $job_name = $type == 'regular' ? 'massgov.populate --sanitize' : 'massgov.populate --super-sanitize';
      if ($item->workflows->job_name == $job_name) {
        $job = $item->build_num;
        break;
      }
    }

    $this->logger()->notice('Start get artifact URL');
    $url = DeployCommands::CIRCLE_URI_PROJECT . "/$job/artifacts";
    $options = ['auth' => [DeployCommands::getTokenCircle()]];
    $response = $client->request('GET', $url, $options);
    DeployCommands::exceptionIfFailed($response);
    $items = json_decode((string)$response->getBody());
    $artifact_url = $items->items[0]->url;

    $path_tarball = ".ddev/db_snapshots/$type.tar.gz";
    $this->logger()->notice('Start artifact download and decompress.');
    $cmd = 'curl -L -o - ' . $artifact_url . ' | tar -xvz';
    $this->processManager()->shell($cmd)->mustRun();
    $this->logger()->notice('Start ddev snapshot restore.');
    $cmd = 'ddev snapshot restore ' . $type;
    $this->processManager()->shell($cmd)->mustRun();

    $this->logger()->success('Snapshot has been restored.');
  }
}

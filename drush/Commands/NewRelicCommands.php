<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Drush;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;

#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class NewRelicCommands extends DrushCommands {

  #[CLI\Option(name: 'nrname', description: 'New Relic transaction name.')]
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: '*')]
  public function optionsetNrName($options = ['nrname' => self::REQ]) {
  }

  /**
   * Set NR name.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
  public function name($result, CommandData $commandData) {
    if ($commandData->input()->hasOption('nrname') && $commandData->input()->getOption('nrname')) {
      $name = $commandData->input()->getOption('nrname');
      $nr_api_key = getenv('MASS_NEWRELIC_LICENSE_KEY');
      $nr_account_id = getenv('MASS_NEWRELIC_APPLICATION');

      if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
        $environment = "{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}";
      }
      else {
        $environment = getenv('TUGBOAT_ROOT') ? 'tugboat' : 'local';
      }

      $startTime = \Drupal::time()->getRequestMicroTime();
      $endTime = \Drupal::time()->getCurrentMicroTime();
      $duration = $endTime - $startTime;

      $status = !$result ? 'success' : json_encode($result);

      $stack = $this->getStack();
      $client = new Client(['handler' => $stack]);
      $options = [
        'headers' => [
          'Api-Key' => $nr_api_key,
        ],
        'json' => [
          [
            'eventType' => 'drushCommand',
            'name' => $name,
            'environment' => $environment,
            'status' => $status,
            'duration' => $duration,
          ],
        ],
      ];

      $response = $client->request('POST', "https://gov-insights-collector.newrelic.com/v1/accounts/{$nr_account_id}/events", $options);
      $code = $response->getStatusCode();
      if ($code >= 400) {
        throw new \Exception('New Relic API response was a ' . $code . '. Use -v for more Guzzle information.');
      }

      $this->logger()->success("Event named {$name} sent to New Relic.");
    }
  }

  /**
   * Use our logger - https://stackoverflow.com/questions/32681165/how-do-you-log-all-api-calls-using-guzzle-6.
   */
  protected function getStack(): HandlerStack {
    $stack = HandlerStack::create();
    $stack->push(Middleware::log($this->logger(), new MessageFormatter(Drush::verbose() ? MessageFormatter::DEBUG : MessageFormatter::SHORT)));
    return $stack;
  }

}

<?php

namespace Drush\Commands;

use Acquia\Cloud\Api\CloudApiClient;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\Util\Shell;
use Drupal\Core\Cache\Cache;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Utils\StringUtils;
use Webmozart\PathUtil\Path;

/**
 * Class DeployCommands.
 */
class DeployCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  var $site = 'prod:massgov';

  /**
   * Write the download link for the most recent database backup to stdout.
   *
   * @param string $target Target environment. Recognized values: dev, cd, test, feature1, feature2, feature3, feature4, feature5, prod.
   * @param string $type Backup type. Recognized values: ondemand, daily.
   *
   * @usage drush ma:latest-backup-url prod
   *   Fetch a link to the latest database backup from production.
   *
   * @command ma:latest-backup-url
   *
   * @throws \Drush\Exceptions\UserAbortException
   * @throws \Exception
   */
  public function latestBackupUrl($target, $type = null) {
    // Build Cloud API client connection.
    $cloudapi = CloudApiClient::factory(array(
      // Easiest way to provide creds is in a .env file. See /.env.example
      'username' => getenv('AC_API_USER'),
      'password' => getenv('AC_API_KEY'),
    ));

    $backups = (array) $cloudapi->databaseBackups($this->site, $target, 'massgov');

    // Ignore backups that are still in progress, and of wrong type.
    // The 'use' keyword is described at https://bryce.fisher-fleig.org/blog/php-what-does-function-use-syntax-mean/index.html.
    $backups = array_filter($backups, function($backup) use ($type) {
      return $backup['completed'] > 0 && (is_null($type) || $backup['type'] == $type);
    });

    // Use the last backup.
    $backup = end($backups);
    if($backup) {
      return $backup['link'];
    }
    throw new \Exception('No usable backups were found.');
  }

  /**
   * Deploy code and database (if needed).
   *
   * Copies Prod DB to target environment, then runs config import, updb,
   * varnish purge, etc.
   *
   * @param string $target Target environment. Recognized values: dev, cd,
   *   test, feature1, feature2, feature3, feature4, feature5, prod.
   * @param string $git_ref Tag or branch to deploy. Must be pushed to Acquia.
   * @param array $options The options list.
   * @usage drush ma-deploy test tags/build-0.6.1
   *   Deploy build-0.6.1 tag to the staging environment.
   * @aliases ma-deploy
   * @deploy
   *
   * @command ma:deploy
   *
   * @throws \Drush\Exceptions\UserAbortException
   * @throws \Exception
   */
  public function deploy($target, $git_ref, array $options = ['cache-rebuild' => TRUE]) {
    $self = $this->siteAliasManager()->getSelf();

    // For production deployments, prompt the user if they are sure. If they say no, exit.
    $is_prod = ($target === 'prod');
    if ($is_prod) {
      $this->confirmProd();
    }

    $this->logger()->success('Starting deployment of {revision} to {target} at {time}', [
      'target' => $target,
      'revision' => $git_ref,
      'time' => $this->getTimestamp()
    ]);

    $targetRecord = $this->siteAliasManager()->get('@' . $target);

    // Build Cloud API client connection.
    $cloudapi = CloudApiClient::factory(array(
      // Easiest way to provide creds is in a /.env file. See /.env.example.
      'username' => getenv('AC_API_USER'),
      'password' => getenv('AC_API_KEY'),
    ));

    // Copy database, but only for non-prod deploys and when refresh-db is set.
    if (!$is_prod && $options['refresh-db']) {
      // This section resembles ma-refresh-local --db-prep-only. We don't call that
      // since we can't easily make Cloud API calls from Acquia servers, and we
      // don't need to sanitize here.
      $process = Drush::drush($self, 'ma:latest-backup-url', ['prod']);
      $process->mustRun();
      $url = $process->getOutput();
      $this->logger()->success('Backup URL retrieved.');

      // Download the latest backup.
      // Directory set by https://jira.mass.gov/browse/DP-12823.
      $tmp =  Path::join('/mnt/tmp', $_SERVER['REQUEST_TIME'] . '-db-backup.sql.gz');
      $bash = ['wget', '-q', '--continue', trim($url), "--output-document=$tmp"];
      $process = Drush::siteProcess($targetRecord, $bash);
      $process->mustRun($process->showRealtime());
      $this->logger()->success('Database downloaded from backup.');

      // Drop all tables.
      $process = Drush::drush($targetRecord, 'sql:drop');
      $process->mustRun();
      $this->logger()->success('Dropped all tables.');

      // Import the latest backup.
      // $bash = ['zgrep', '--line-buffered', '-v', '-e', '^INSERT INTO \`cache_', '-e', '^INSERT INTO \`migrate_map_', '-e', "^INSERT INTO \`config_log", '-e', "^INSERT INTO \`key_value_expire", '-e', "^INSERT INTO \`sessions", $tmp, Shell::op('|'), Path::join($targetRecord->root(), '../vendor/bin/drush'), '-r', $targetRecord->root(), '-v', 'sql:cli'];
      // $bash = '-e "^INSERT INTO \`migrate_map_" -e "^INSERT INTO \`config_log" -e "^INSERT INTO \`key_value_expire" -e "^INSERT INTO \`sessions" ' . $tmp . ' | drush -vvv sql:cli';
      $bash = ['cd', $targetRecord->root(), Shell::op('&&'), '../scripts/ma-import-backup', $tmp];
      $process = Drush::siteProcess($targetRecord, $bash);
      $process->disableOutput();
      $process->mustRun();
      $this->logger()->success('Database imported from backup.');

      // Delete tmp file.
      $bash = ['test', '-f', $tmp, Shell::op('&&'), 'rm', $tmp];
      $process = Drush::siteProcess($targetRecord, $bash);
      $process->mustRun();
      $this->logger()->success('Temporary file deleted. ' . $tmp);
    }

    if ($options['skip-maint'] == FALSE) {
      // Turn on Maint mode.
      $args = array('system.maintenance_mode', 1);
      $state_options = array('input-format' => 'integer');
      $process = Drush::drush($targetRecord, 'state:set', $args, $state_options);
      $process->mustRun();
      $this->logger()->success("Maintenance mode enabled in $target.");
    }

    // Deploy the new code.
    $code = $cloudapi->pushCode($this->site, $target, $git_ref);
    $id = $code->id();
    $this->waitForTaskToComplete($cloudapi, $this->site, $id);

    if ($options['cache-rebuild']) {
      // Rebuild cache_discovery ONLY.  This allows new plugins to be picked up
      // immediately, without opening the door to potential fatal errors from
      // the entire cache being dumped.
      $process = Drush::drush($targetRecord, 'cache:clear', ['plugin'], ['debug' => TRUE]);
      $process->mustRun();
      $this->logger()->success('Plugin cache clear complete.');
    }

    // Run any pending DB updates.
    // This goes before config import per https://www.drupal.org/node/2628144.
    $entity_options = array('no-post-updates' => TRUE, 'verbose' => TRUE);
    $process = Drush::drush($targetRecord, 'updb', [], $entity_options);
    $process->mustRun($process->showRealtime());
    $this->logger()->success("Database and entity updates completed in $target.");

    // Import new config.
    $process = Drush::drush($targetRecord, 'config:import');
    $process->mustRun($process->showRealtime());
    $this->logger()->success("Configuration imported in $target.");

    // Run any pending post-deploy steps.
    // This goes after config import.
    $entity_options = array('post-updates' => TRUE, 'verbose' => TRUE, 'no-cache-clear' => TRUE);
    $process = Drush::drush($targetRecord, 'updb', [], $entity_options);
    $process->mustRun($process->showRealtime());
    $this->logger()->success("Post updates completed in $target.");

    if ($options['cache-rebuild']) {
      // Do a final cache rebuild to allow all changes to be displayed.  This
      // step was added as a catch-all... in theory all caches that need to be
      // cleared should already be cleared at this point, however there are some
      // places we've still encountered issues:
      // 1. New/Updated page views do not show up until router cache clear.
      // 2. Mayflower changes do not show up immediately.
      // This step could be removed if workarounds are found for those two items.
      $process = Drush::drush($targetRecord, 'cache:rebuild', [], ['verbose' => TRUE]);
      // To avoid occasional rmdir errors, disable Drush cache for this call.
      $process->setEnv(['DRUSH_PATHS_CACHE_DIRECTORY ' => '/dev/null']);
      $process->mustRun();
      $this->logger()->success('Cache rebuild complete.');
    }

    // Get a list of all an environment's domains.
    // Note: This also returns load balancer URLs.
    $domains = $cloudapi->domains($this->site, $target);
    foreach ($domains as $domain) {
      // Skip Load Balancers.
      if (!preg_match('/.*\.elb\.amazonaws\.com$/', $domain)) {
        $domains_web[] = $domain;
      }
    }

    if ($options['varnish']) {
      // Purge Varnish cache.
      foreach ($domains_web as $domain) {
        // Clear the cache for the domain.
        $cloudapi->purgeVarnishCache($this->site, $target, $domain);
        $this->logger()->success("Purged full Varnish cache for $domain in $target environment.");
      }
    }
    else {
      // Enqueue purging of QAG pages.
      $sql = "SELECT nid FROM node_field_data WHERE title LIKE '%_QAG%'";
      $process = Drush::drush($targetRecord, 'sql:query', [$sql], ['verbose' => TRUE]);
      $process->mustRun();
      $out = $process->getOutput();
      $nids = array_filter(explode("\n", $out));
      foreach ($nids as $nid) {
        $tags[] = "node:$nid";
      }
      $process = Drush::drush($targetRecord, 'cache:tags', [implode(',', $tags)], ['verbose' => TRUE]);
      $process->mustRun();

      // Enqueue purging of notable URLs. Don't use tags to avoid over-purging.
      // Empty path is the homepage
      $paths = ['', 'orgs/office-of-the-governor'];
      foreach ($domains_web as $domain) {
        foreach ($paths as $path) {
          $expressions[] = 'url ' . 'https://' . $domain . '/' . $path . ',';
        }
      }
      $process = Drush::drush($targetRecord, 'p:queue-add', $expressions, ['verbose' => TRUE]);
      $process->mustRun();

      $this->logger()->success("Selective Purge enqueued at $target.");
    }

    if ($options['skip-maint'] == FALSE) {
      // Disable Maintenance mode.
      $args = array('system.maintenance_mode', '0');
      $state_options = ['input-format' => 'integer'];
      $process = Drush::drush($targetRecord, 'state:set', $args, $state_options);
      $process->mustRun();
      $this->logger()->success("Maintenance mode disabled in $target.");
    }

    // Process purge queue.
    $process = Drush::drush($targetRecord, 'p:queue-work', [], ['finish' => TRUE, 'verbose' => TRUE]);
    $process->mustRun();
    $this->logger()->success("Purge queue worker complete at $target.");

    // Log a new deployment at New Relic.
    if ($is_prod) {
      $this->newRelic($git_ref, getenv('AC_API_USER'), getenv('MASS_NEWRELIC_APPLICATION'), getenv('MASS_NEWRELIC_KEY'));
    }
    $done = $this->getTimestamp();
    $this->io()->success("Deployment completed at {$done}");
  }

  /**
   * Validate the target name.
   *
   * @hook validate
   *
   * @throws \Exception
   */
  public function validate(CommandData $commandData) {
    $target = $commandData->input()->getArgument('target');
    $available_targets = ['dev', 'cd', 'test', 'feature1', 'feature2', 'feature3', 'feature4', 'feature5', 'prod', 'ra'];
    if (!in_array($target, $available_targets)) {
      throw new \Exception('Invalid argument: target. \nYou entered "' . $target . '". Target must be one of: ' . implode(', ', $available_targets));
    }
  }

  /**
   * @hook option @deploy
   *
   * @option refresh-db Copy the DB from Prod to replace target environment's
   *   DB.
   * @option skip-maint Skip maintenance mode enable/disable.
   * @option cache-rebuild Rebuild caches as needed during deployment.
   * @option varnish Purge Varnish fully at end of deployment. Otherwise, do minimalist purge.
   */
  public function options($options = ['refresh-db' => FALSE, 'skip-maint' => FALSE, 'cache-rebuild' => TRUE, 'varnish' => FALSE]) {

  }

  /**
   * Pause until a given task is completed.
   *
   * This function handles Cloud API 503 errors, and will ignore up to five 503s
   * before failing.
   *
   * @param \Acquia\Cloud\Api\CloudApiClient $cloudapi
   * @param string $site Site name
   * @param int $id Id
   *   The task ID.
   *
   * @return bool
   *   Final outcome. Always true.
   *
   * @throws \Exception
   */
  public function waitForTaskToComplete(CloudApiClient $cloudapi, $site, $id) {
    $task_complete = FALSE;
    $cloud_api_failures = 0;

    while ($task_complete !== TRUE) {
      try {
        $task_status = $cloudapi->task($site, $id);
        if ($task_status->state() == 'done') {
          $task_complete = TRUE;
          $this->logger()->success(dt('!desc is complete: Task !task_id.', array('!desc' => $task_status->description(), '!task_id' => $id)));
        }
        elseif ($task_status->state() == 'failed') {
          throw new \Exception(dt("!desc - Task !task_id failed:\n!logs", array('!desc' => $task_status->description(), '!task_id' => $id, '!logs' => $task_status->logs())));
        }
        else {
          $this->logger()->notice(dt('!desc: Will re-check Task !task_id for completion in 5 seconds.', array('!desc' => $task_status->description(), '!task_id' => $id)));
          sleep(5);
        }
      }
      catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
        if ($e->getCode() == 503) {
          $cloud_api_failures++;
          if ($cloud_api_failures >= 5) {
            throw new Exception('Cloud API returned 5 or more 503s, indicating failure to complete.');
          }
        }
      }
    }
    return $task_complete;
  }

  /**
   * Post new deployment to New Relic.
   *
   * @param $git_ref
   * @param $email
   * @param $application
   * @param $api_key
   */
  public function newRelic($git_ref, $email, $application, $api_key) {
    $cmd = <<<EOT
curl -X POST 'https://api.newrelic.com/v2/applications/$application/deployments.json' \
     -H 'X-Api-Key:$api_key' -i \
     -H 'Content-Type: application/json' \
     -d \
'{
  "deployment": {
    "revision": "$git_ref",
    "changelog": "",
    "description": "",
    "user": "$email"
  }
}'
EOT;
    if (!Drush::shell($cmd)) {
      $this->logger()->warning('Failed to create a Deployment at New Relic');
    }
  }

  /**
   * Get a string representing the current time in EST.
   *
   * @return string
   */
  private function getTimestamp() {
    return (new \DateTime(NULL, new \DateTimeZone('America/New_York')))->format('Y-m-d g:i:s A');
  }

  /**
   * @return string|void
   * @throws \Drush\Exceptions\UserAbortException
   */
  protected function confirmProd(): void
  {
    if (!$this->io()
      ->confirm('This is a Production deployment. Are you damn sure?')) {
      throw new UserAbortException();
    }
  }

  /**
   * Invalidate by cache tags.
   *
   * Note: This is a temporary backport of a Drush 10 feature.
   * Remove when we go to Drush 10.
   *
   * @command cache:tags
   * @param string $tags A comma delimited list of cache tags to clear.
   * @aliases ct
   * @bootstrap full
   * @usage drush cache:tag node:12,user:4
   *   Purge content associated with two cache tags.
   */
  public function tags($tags)
  {
    $tags = StringUtils::csvToArray($tags);
    Cache::invalidateTags($tags);
    $this->logger()->success(dt("Invalidated tag(s): !list.", ['!list' => implode(' ', $tags)]));
  }

}

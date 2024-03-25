<?php

namespace Drupal\mass_serializer\Drush\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\mass_serializer\CacheEndpoint;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Runtime\DependencyInjection;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Class MassSerializerCommands.
 *
 * @package Drupal\mass_serializer\Commands
 */
class MassSerializerCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected CacheEndpoint $cacheEndpoint,
    private SiteAliasManagerInterface $siteAliasManager
  ) {
    parent::__construct();
  }

  /**
   * MS Cache.
   *
   * @param string $machine_name
   *   Machine name for the endpoint to be cached, currently only works for "documents_by_filter".
   * @param int $term_id
   *   Term id for the odsm endpoint to be cached, e.g. 3111.
   *
   * @command ms:cache
   * @aliases mserc,mass-serializer-cache
   */
  public function serializerCache($machine_name, $term_id) {
    $this->cacheEndpoint->cacheSave($machine_name, [$term_id]);
  }

  /**
   * MS Render Partial.
   *
   * @param string $machine_name
   *   Machine name for the endpoint to be cached, currently only works for "documents_by_filter".
   * @param string $display
   *   Machine name for the view display to render, e.g. rest_export_1.
   * @param string $filename
   *   Temp file name stream wrapper to use.
   * @param int $term_id
   *   Term id for the odsm endpoint to be cached, e.g. 3111.
   * @param int $offset
   *   Number of records to skip.
   *
   * @aliases mserp,mass-serializer-render-partial
   * @command ms:render-partial
   */
  public function serializerRenderPartial($machine_name, $display, $filename, $term_id, $offset = 0) {
    $this->cacheEndpoint->renderPartial($machine_name, $display, $filename, [$term_id], $offset);
  }

  /**
   * MS Cache All.
   *
   * @param string $machine_name
   *   Machine name for the endpoint to be cached, currently only works for "documents_by_filter".
   * @param int $limit
   *   (Optional) Number of Terms to be processed during batch, e.g. 10. Mainly for testing purposes.
   *
   * @command ms:cache-all
   * @aliases mserca,mass-serializer-cache-all
   */
  public function serializerCacheAll($machine_name, $limit = 0) {
    // Avoid "the Mysql server went away".
    $this->cacheEndpoint->setDbTimeout();

    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', "user_organization");
    $tids = $query->accessCheck(FALSE)->execute();

    $this->output()->writeln('Total number of Organizations to process: ' . ($limit != 0 ? $limit : count($tids)));

    $processed = 0;
    foreach ($tids as $tid) {
      $this->logger()->debug('Calling mserc drush command with organization id: ' . $tid);
      $process = Drush::drush($this->siteAliasManager->getSelf(), 'mserc', [$machine_name, $tid], Drush::redispatchOptions());
      $process->mustRun();
      $processed++;

      // If limit is reached then stop processing organizations.
      // This is mainly for testing purposes (you can specify only 2 items
      // for example to see if the data is exported as expected).
      if ($limit != 0 && $limit == $processed) {
        break;
      }
    }

    $this->logger()->success('Finished generating Json files.');
  }

  /**
   * MS Merge file. Internal.
   *
   * @param string $cachename
   *   String for the filename.
   * @param string $filenames
   *   All files created for org with the given offset.
   *
   * @command ms:merge-file
   * @aliases msmf,mass-serializer-merge-file
   */
  public function serializerMergeFile($cachename, $filenames) {
    $this->cacheEndpoint->mergeFiles($cachename, explode(' ', $filenames));
  }

}

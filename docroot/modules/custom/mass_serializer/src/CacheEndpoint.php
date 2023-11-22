<?php

namespace Drupal\mass_serializer;

use Drupal\Core\File\FileSystemInterface;
use Drupal\views\Views;
use Drush\Drush;
use Exception;

/**
 * Class CacheEndpoint.
 *
 * @package Drupal\mass_serializer
 */
class CacheEndpoint {

  /**
   * The batch size of cache operations.
   *
   * Fewer items per page uses less system memory.
   * This number must match one of the values in the view to work.
   * 5, 10, 25, 50, 100, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000.
   *
   * @var int
   */
  protected $itemsPerPage = 2000;

  /**
   * The public files directory to store caches.
   *
   * @var string
   */
  protected $publicDirectory = 'public://datajson/';

  /**
   * The display id of the view for normal display.
   *
   * @var string
   */
  protected $display = 'rest_export_documents_by_contributor';

  /**
   * The display id of the view with no headers.
   *
   * @var string
   */
  protected $displayNoHeaders = 'rest_export_1';

  /**
   * The display id of the view with no headers.
   *
   * @var \Drupal\mass_serializer\RenderEndpoint
   */
  protected $renderEndpoint;

  /**
   * The display id of the view with no headers.
   *
   * @var \Consolidation\SiteAlias\SiteAliasManager
   */
  protected $aliasManager;

  /**
   * The display id of the view with no headers.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new CacheEndpoint object.
   *
   * @param \Drupal\mass_serializer\RenderEndpoint $render_endpoint
   *   Render Endpoint service.
   */
  public function __construct(RenderEndpoint $render_endpoint) {
    $this->renderEndpoint = $render_endpoint;
    $this->aliasManager = Drush::service('site.alias.manager');
    $this->logger = Drush::service('logger');
  }

  /**
   * Retrieve the file name based on $args.
   *
   * @param string $api
   *   Name of the endpoint you are saving.
   * @param array $args
   *   Helps generate unique temp file names.
   * @param bool $public
   *   Choose between public or temp directory.
   * @param int $offset
   *   If using multiple pages, supply an offset to differentiate args.
   *
   * @return string
   *   Filename to save cached output to.
   */
  public function cacheName($api, array $args, $public = TRUE, $offset = 0) {
    $stream = $public ? $this->publicDirectory : 'temporary://';
    $name = $stream . $api;

    foreach ($args as $argument) {
      $name .= '_' . $argument;
    }

    if ($offset || !$public) {
      $name .= '_' . $offset;
    }

    return $name . '.json';
  }

  /**
   * Check that the cachce file exists in the public files directory.
   *
   * @param string $api
   *   Name of the endpoint you are checking.
   * @param array $args
   *   Arguments to supply to the view.
   *
   * @return bool
   *   Will be TRUE if the cache file is in the public directory.
   */
  public function cacheExists($api, array $args) {
    return file_exists($this->cacheName($api, $args));
  }

  /**
   * Save a copy of the View output. Called by drush.
   *
   * @param string $api
   *   Name of the endpoint you are saving.
   * @param array $args
   *   Arguments to supply to the view.
   */
  public function cacheSave($api, array $args) {
    $self = $this->aliasManager->getSelf();
    try {
      \Drupal::service('file_system')->prepareDirectory($this->publicDirectory, FileSystemInterface::CREATE_DIRECTORY);
      if (!\Drupal::service('file_system')->prepareDirectory($this->publicDirectory)) {
        $this->logger->error($this->publicDirectory . 'does not exist or is not writeable.');
        return;
      }

      $this->logger->success('Beginning processing for endpoint (this could take a while)');

      $count = $this->countRows($args[0]);

      // Message on zero items. Still generate the cache file.
      if (!$count) {
        $this->logger->success('Zero items in feed.');
      }

      // If the feed is only one page, simply save the file directly to public files.
      if ($this->itemsPerPage >= $count) {
        $filename = $this->cacheName($api, $args, TRUE);
        $this->renderPartial($api, $this->display, $filename, $args, TRUE);
        $this->logger->success('Single page finished. ' . $filename . ' saved.');
        return;
      }

      // Set Mysql timeout.
      $this->setDbTimeout();

      // If the feed has multiple pages create temp files per page.
      $this->logger->success($count . ' items, starting batches of ' . $this->itemsPerPage . '.');
      $filenames = [];
      for ($offset = 0; $offset < $count; $offset += $this->itemsPerPage) {
        $filename = $this->cacheName($api, $args, FALSE, $offset);
        $filenames[] = $filename;

        // Spawn a separate process so memory does not run out.
        $mserp_args = [$api, $this->display, $filename, $args[0], $offset];
        $process = Drush::drush($self, 'mass-serializer-render-partial', $mserp_args, Drush::redispatchOptions());
        $process->mustRun();
      }

      // Spawn a separate process so memory does not run out.
      $msmf_args = [
        $this->cacheName($api, $args, TRUE),
        implode(' ', $filenames),
      ];
      $process = Drush::drush($self, 'mass-serializer-merge-file', $msmf_args, Drush::redispatchOptions());
      $process->mustRun();
    }
    catch (Exception $e) {
      $this->logger->error('Exception: ' . $e->getMessage());
    }
  }

  /**
   * When long transactions take place, the mysql server can hang up the connection.
   */
  public function setDbTimeout() {
    try {
      $result = \Drupal::database()
        ->query('SET SESSION wait_timeout = 7200')
        ->execute();
    }
    catch (Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Count the number of terms that match the id.
   *
   * @param int $id
   *   Term ID to count.
   *
   * @return int
   *   Number of terms that match the given ID.
   */
  public function countRows($id) {
    $query = \Drupal::database()->select('media_field_data', 'm');
    $query->addExpression('COUNT(*)');
    $query->innerJoin('media__field_contributing_organization', 'o', "m.mid = o.entity_id AND (o.deleted = '0' AND o.langcode = m.langcode)");
    $query->condition('m.bundle', 'document');
    $query->condition('m.status', 1);
    $query->condition('o.field_contributing_organization_target_id', $id);
    return $query->execute()->fetchField();
  }

  /**
   * Renders a view for output or saving to disk; called from Drush.
   *
   * @param string $api
   *   Name of the endpoint you are saving.
   * @param string $display
   *   Display ID of the view to use.
   * @param string $filename
   *   Name of the temp file you are saving.
   * @param array $args
   *   Arguments to supply to the view.
   * @param int $offset
   *   If using multiple pages, supply an offset to differentiate args.
   */
  public function renderPartial($api, $display, $filename, array $args, $offset = 0) {
    $view = Views::getView($api);

    if (!$view) {
      throw new Exception('No view returned by this machine name: ' . $api);
    }

    $this->logger->success('View with offset: ' . $offset);

    $view->setItemsPerPage($this->itemsPerPage);
    $view->setOffset($offset);

    $preview = $view->preview($display, $args);

    $file = \Drupal::service('file.repository')->writeData(strval($preview['#markup']), $filename, FileSystemInterface::EXISTS_REPLACE);
    $this->logger->success('Saving partial ' . $filename);
  }

  /**
   * Combine outputs of all files; called from Drush.
   *
   * Memory kept running out so this was moved here.
   *
   * @param string $cachename
   *   Name of the file you are saving.
   * @param array $filenames
   *   Name of the temp files you are combining.
   */
  public function mergeFiles($cachename, array $filenames) {
    $data = [];
    foreach ($filenames as $filename) {
      $result = json_decode(file_get_contents($filename));
      $data = array_merge($data, $result->dataset);
    }
    $result->dataset = $data;

    \Drupal::service('file.repository')->writeData(json_encode($result), $cachename, FileSystemInterface::EXISTS_REPLACE);
    $this->logger->info('All pages combined. ' . count($data) . ' rows. ' . $cachename . ' saved.');
  }

}

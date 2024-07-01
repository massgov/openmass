<?php

namespace Drupal\mass_utility\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientInterface;

final class MassUtilityCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected Connection $connection,
    protected MessengerInterface $messenger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClientInterface $httpClient
  ) {
    parent::__construct();
  }

  /**
   * Save nodes by bundle.
   *
   * @param string $node_types
   *   String of node types (ie bundles) to process and save.
   * @param array $options
   *   The options.
   *
   * @option set-moderation-state
   *   Ensure that all content is set to published.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   *
   * @command ma:save-node
   */
  public function saveNode($node_types, array $options = ['set-moderation-state' => FALSE]) {
    $node_types = StringUtils::csvToArray($node_types);
    $node_bundles = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();
    // Exit if user provided node_types do not match a node_bundle.
    // We handle this separately so if a user passes even one node_type that
    // doesn't exist, they can correct and rerun the whole command.
    foreach ($node_types as $node_type) {
      if (!array_key_exists($node_type, $node_bundles)) {
        throw new \InvalidArgumentException(dt('"@node_type" is not a valid node bundle type. Please update this node type and resubmit.', ['@node_type' => $node_type]));
      }
    }

    // Load nodes and save.
    foreach ($node_types as $node_type) {
      $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
      $nids = $query->condition('type', $node_type)->execute();
      /** @var Drupal\node\Entity\Node[] $nodes */
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        if ($options['set_moderation_state'] && $node->isPublished() && MassModeration::isPrepublish($node)) {
          $node->set('moderation_state', MassModeration::PUBLISHED);
        }
        $node->setRevisionLogMessage('Saved by ma:save-node Drush command.');
        $node->save();
      }
      $node_count = count($nodes);
      $this->logger()->success(dt("Successfully saved " . $node_count . " nodes for " . $node_type . " node bundle."));
    }
  }

  /**
   * Safely deletes supplied media entities and files connected to them.
   *
   * @param string $media_entity_ids
   *   Comma separated string of multiple media entity ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   *
   * @command ma:delete-media-files
   */
  public function deleteMediaFiles($media_entity_ids) {
    // Iterate on all the IDs passed to the command.
    $media_entity_ids = StringUtils::csvToArray($media_entity_ids);
    foreach ($media_entity_ids as $media_entity_id) {
      $entity = $this->entityTypeManager->getStorage('media')->load($media_entity_id);
      $isMediaEntity = ($entity instanceof EntityInterface && $entity->getEntityTypeId() === 'media') ? TRUE : FALSE;
      // Act only on media entities.
      if ($isMediaEntity) {
        $fid = $entity->get('field_upload_file')->target_id;
        if (is_numeric($fid)) {
          // Ensure that the file is not used elsewhere, that it only references
          // one media entity, the expected one.
          $file = $this->entityTypeManager->getStorage('file')->load($fid);
          $fileUsage = \Drupal::service('file.usage')->listUsage($file);
          // NOTE: In the final check below we use "==" and not "==="
          // because the media id sometimes gets picked as a string array key
          // and sometimes as int.
          // See: https://jira.state.ma.us/browse/DP-7930?focusedCommentId=230336#comment-230336
          $fileIsNotUsedElsewhere =
            array_keys($fileUsage) === ['file'] &&
            array_keys($fileUsage['file']) === ['media'] &&
            array_keys($fileUsage['file']['media']) == ["$media_entity_id"];
          if ($fileIsNotUsedElsewhere) {
            // Delete the media entity first, then the file.
            $fileFullPath = $file->getFileUri();
            $entity->delete();
            $file->delete();
            $this->logger()->success(dt('Deleted media entity @mid and file entity @fid', [
              '@mid' => $media_entity_id,
              '@fid' => $fid,
            ]));

            if (file_exists($fileFullPath)) {
              // Delete file from filesystem.
              $this->logger()->error(dt('Unable to delete file from filesystem. Please manually delete @file', [
                '@file' => $fileFullPath,
              ]));
            }
            else {
              $this->logger()->success(dt('Deleted file from filesystem @file', [
                '@file' => $fileFullPath,
              ]));
            }
          }
          else {
            $this->logger()->error(dt("Multiple file references found. Media @mid and File @fid were not deleted.", [
              '@mid' => $media_entity_id,
              '@fid' => $fid,
            ]));
          }
        }
        else {
          $this->logger()->error(dt("Incorrect file ID. Media @mid and File @fid were not deleted.", [
            '@mid' => $media_entity_id,
            '@fid' => $fid,
          ]));
        }
      }
      else {
        $this->logger()->error(dt("There was no media entity found with ID @id", [
          '@id' => $media_entity_id,
        ]));
      }
    }
  }

  /**
   * Takes in google analytics 404 report and breaks it down by causes of 404.
   *
   * @param string $fullpath_ga_404_csv_file
   *   Full path to the 404's report downloaded form Google Analytics.
   *
   * @throws \Exception
   *
   * @usage drush ma:report-404-causes /fullpath/input.csv > output.csv
   *   Reads 404 data from supplied google analytics csv file, and adds root cause beside each url.
   *
   * @command ma:report-404-causes
   */
  public function report404Causes($fullpath_ga_404_csv_file) {
    if (!file_exists($fullpath_ga_404_csv_file)) {
      $this->logger()->error(dt("File with Google analytics 404 csv report does not exist."));
      throw new \Exception("File with Google analytics 404 csv report does not exist.");

    }

    // Create a regex to check internal urls, using D8 pathauto patterns.
    $d8massgov_url_segments = [];
    foreach (\Drupal::configFactory()->listAll('pathauto.pattern.') as $pattern_config_name) {
      $pattern_config = \Drupal::configFactory()->getEditable($pattern_config_name);
      // Collect those patterns that have a constant string in first segment.
      // Eg: /how-to/[node-title]
      // @todo Collect patterns with dynamic tokens in first segment.
      // Eg: /[node:field_advisory_type_tax]/[node:title].
      $first_segment = reset(array_filter(explode('/', $pattern_config->get('pattern'))));
      if (substr($first_segment, 0, 1) !== '[') {
        array_push($d8massgov_url_segments, $first_segment);
      }
    }
    $d8massgov_url_regex = '/^\/(' . implode('|', $d8massgov_url_segments) . ')(?=\/|$)/';

    // Iterate through the 404 report and classify each url there
    // with what is causing that 404 page.
    $ga_404_csv_file = new \SplFileObject($fullpath_ga_404_csv_file);
    $ga_404_csv_file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
    foreach ($ga_404_csv_file as $row) {
      [$event_404_action, $event_404_url, $event_404_count] = $row;
      // GA report has comma's in count (Eg: 43,216) so we remove them,
      // and make the count a "pure integer" value.
      $event_404_count = intval(str_replace(",", "", $event_404_count));
      if ($event_404_action != "Page 404") {
        continue;
      }
      elseif (preg_match($d8massgov_url_regex, parse_url($event_404_url, PHP_URL_PATH))) {
        printf("MISSING INTERNAL REDIRECT, %d, %s\n", $event_404_count, $event_404_url);
      }
      else {
        printf("SOME OTHER REASON, %d, %s\n", $event_404_count, $event_404_url);
      }
    }
  }

  /**
   * Cleanup site revisions prior to a certain timestamp.
   *
   * @option batch
   *   Determines how many records to process at once. Defaults to 50.
   * @option bundle
   *   The node bundle(s) to use when processing revisions. Defaults to all.
   * @option idlist
   *   Allows targeting a specific list of nodes by ID.
   * @option limit
   *   Determines how many records to process total. Defaults to 200.
   * @option offset
   *   Determines how many records to skip. Defaults to 0.
   * @option timestamp
   *   The unix timestamp to use when processing revisions. Defaults to a timestamp that is 14 months ago.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   *
   * @command ma:queue-revision-cleanup
   */
  public function queueRevisionsCleanup(array $options = [
    'batch' => 50,
    'bundle' => NULL,
    'idlist' => NULL,
    'limit' => 200,
    'offset' => 0,
    'timestamp' => NULL,
  ]) {
    extract($options);
    $bundle = !empty($options['bundle']) ? explode(',', $options['bundle']) : [];
    $idlist = !empty($options['idlist']) ? explode(',', $options['idlist']) : [];
    // If the user didn't provide a timestamp, then default to a year ago from
    // today's date.
    $timestamp = !empty($options['timestamp']) ? $options['timestamp'] : strtotime('14 months ago');

    // Setup the variables that will be used by the while loop.
    $finished = FALSE;
    $ops = [];
    $counter = ceil($limit / $batch);
    $batch_size = $batch;
    $offset_size = $offset;

    if ($limit < $batch) {
      $batch_size = $limit;
    }
    while (!$finished) {
      $query = $this->connection
        ->select('node_field_revision', 'r')
        ->fields('r', ['nid']);
      $query->join('node_field_data', 'n', 'n.nid = r.nid');
      $query->condition('r.changed', $timestamp, '<');
      $query->where('r.vid <> n.vid');

      if (!empty($idlist)) {
        $query->condition('r.nid', $idlist, 'IN');
      }
      if (!empty($bundle)) {
        $query->condition('n.type', $bundle, 'IN');
      }

      $query->orderBy('r.nid', 'ASC')
        ->groupBy('r.nid')
        ->having('COUNT(*) > 1')
        ->range($offset_size, $batch_size);
      $nids = $query->execute()->fetchCol();

      // Update the counter and offset for the next loop.
      $counter--;
      $offset_size += $batch_size;

      // Limit the batch size based on the limit value. This prevents too many
      // nodes from being queued up for revision clean up.
      if ($counter == 1 && ($limit % $batch != 0)) {
        $batch_size = $limit % $batch;
      }

      // Stop the loop if it has completed its run or it has run out of content
      // to queue.
      $finished = $counter === 0 || count($nids) < $batch_size;

      if (!empty($nids)) {
        $this->logger()->info(dt('Found @count nodes in need of clean up.', ['@count' => count($nids)]));
        $ops[] = [
          '\Drupal\mass_utility\BatchService::populateRevisionsCleanupQueue',
          [$nids, $timestamp, $batch],
        ];
      }
      else {
        $this->messenger->addMessage('No revisions found in need of clean up.');
        $this->logger()->info('No revisions found in need of clean up.');
        $finished = TRUE;
      }

    }

    // Build and run the batch job that will queue the nodes that have revisions
    // identified for removal for later processing.
    $batch = [
      'title' => dt('Processing nodes'),
      'init_message' => dt('Queueing nodes for revisions clean up...'),
      'operations' => $ops,
      'finished' => '\Drupal\mass_utility\BatchService::populateRevisionsCleanupQueueFinished',
    ];

    // Set the batch to start processing nodes.
    batch_set($batch);
    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    drush_backend_batch_process();

  }

}

<?php
declare(strict_types=1);

namespace Drupal\mass_admin_pages\Commands;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

/**
 * Add nodes to be checked for similar titles to identify duplicate content.
 */
class SimilarTitles extends DrushCommands {

  public const STATE_KEY = 'mass_admin_pages.similar_node_titles';

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private MemoryCacheInterface $entityMemoryCache;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private QueueInterface $queue;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, MemoryCacheInterface $entityMemoryCache, StateInterface $state, QueueFactory $queueFactory) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->entityMemoryCache = $entityMemoryCache;
    $this->state = $state;
    $this->queue = $queueFactory->get('mass_admin_pages.similar_titles');
  }

  /**
   * @command ma:similar-titles
   */
  public function queue(): void {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $m */
    $query = $this->entityTypeManager->getStorage('node')->getQuery();

    // Limit to published nodes of the given content types.
    $query->condition($this->entityTypeManager->getDefinition('node')->getKey('bundle'), [
      'how_to_page',
      'service_page',
      'topic_page',
    ], 'IN');
    $query->condition('status', 1);
    $results = $query->execute();

    // @todo We don't delete node data for nodes that have been unpublished.
    // Load nodes in groups of 1000 so we can clear the memory cache.
    $chunked = array_chunk($results, 1000);
    $node_titles = [];
    foreach ($chunked as $chunk) {
      $nodes = $this->entityTypeManager->getStorage('node')
        ->loadMultiple($chunk);
      foreach($nodes as $node) {
        $node_titles[$node->id()] = $node->label();
      }

      /** @noinspection DisconnectedForeachInstructionInspection */
      $this->entityMemoryCache->deleteAll();
    }

    // Since we can't control for edits made after we make the comparison, we
    // take this opportunity to load all node titles now of matching nodes, and
    // them save them into state for use by the queue. We don't use cache as
    // memcache has a limit of 1MB, and we expect this data to be 5-20MB.
    $this->state->set(self::STATE_KEY, $node_titles);

    // Now, queue the nodes to check. We also group this by 1000 as we don't
    // really need to process nodes individually.
    $queue_chunk = array_chunk($node_titles, 1000, TRUE);
    foreach ($queue_chunk as $chunk) {
      $this->queue->createItem($chunk);
    }
  }

}

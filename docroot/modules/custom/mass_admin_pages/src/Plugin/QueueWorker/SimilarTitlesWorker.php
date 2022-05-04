<?php

declare(strict_types=1);

namespace Drupal\mass_admin_pages\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\mass_admin_pages\Commands\SimilarTitles;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @see \Drupal\mass_admin_pages\Commands\SimilarTitles
 *
 * @QueueWorker(
 *   id="mass_admin_pages.similar_titles",
 *   title=@Translation("Similar Titles"),
 * )
 */
class SimilarTitlesWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var mixed
   */
  private $node_titles;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_titles = $state->get(SimilarTitles::STATE_KEY);
    $this->database = $database;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('database')
    );
  }

  /**
   * Process the queue item of ~1000 nodes.
   *
   * This is somewhat of an expensive operation because we have to compare every
   * single node against every single other node. We could consider filtering
   * this list, such as only comparing in one direction (new to old), accepting
   * that similar_text() gives different non-comparable results when you flip
   * its arguments around.
   *
   * @param $data
   *
   * @return void
   */
  public function processItem($data) {
    foreach ($data as $nid => $title) {
      foreach ($this->node_titles as $compare_nid => $compare_title) {
        if ($nid === $compare_nid) {
          continue;
        }
        $matching_characters = similar_text($title, $compare_title, $percent);
        if ($percent > 80) {
          $this->database->upsert('mass_admin_pages_similar_titles')
            ->key('nid')
            ->fields([
            'nid' => $nid,
            'title' => $title,
            'other_nid' => $compare_nid,
            'other_title' => $compare_title,
            'percent' => $percent,
          ])->execute();
        }
        else {
          $this->database->delete('mass_admin_pages_similar_titles')->condition('nid', $nid);
        }
      }
    }

  }

}

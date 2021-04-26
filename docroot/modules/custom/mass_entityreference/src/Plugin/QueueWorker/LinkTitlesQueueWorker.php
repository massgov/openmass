<?php

namespace Drupal\mass_entityreference\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update matching link titles on cron runs.
 *
 * @QueueWorker(
 *   id = "mass_entityreference_link_titles",
 *   title = @Translation("Link TitlesQueue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class LinkTitlesQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Creates a new LinkTitlesQueueWorker.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct(EntityStorageInterface $node_storage) {
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * Dynamic link text update.
   *
   * Set a blank value for link titles if the referenced node title matches.
   */
  public function processItem($data) {

    $entity_id = end(explode('/', $data->uri_value));

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->nodeStorage->load($entity_id);
    if ($node instanceof NodeInterface) {
      if ($data->title_value === $node->getTitle()) {
        $database = \Drupal::database();
        $database->update($data->table)
          ->fields([
            $data->title_field => '',
          ])
          ->condition('entity_id', $data->entity_id, '=')
          ->condition($data->uri_field, $data->uri_value, '=')
          ->condition($data->title_field, $data->title_value, '=')
          ->execute();
      }
    }
    return;
  }

}

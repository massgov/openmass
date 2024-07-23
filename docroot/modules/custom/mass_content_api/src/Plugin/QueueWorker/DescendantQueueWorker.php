<?php

namespace Drupal\mass_content_api\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process Descendant queue items.
 *
 * @QueueWorker(
 *   id = "mass_content_api_descendant_queue",
 *   title = @Translation("Descendant queue processing"),
 * )
 */
class DescendantQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\mass_content_api\DescendantManager definition.
   *
   * @var \Drupal\mass_content_api\DescendantManagerInterface
   */
  protected $descendantManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DescendantManagerInterface $descendant_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->descendantManager = $descendant_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('descendant_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Allow batching items to queue for faster initial processing.
    if (isset($data->ids)) {
      foreach (Node::loadMultiple($data->ids) as $node) {
        $this->descendantManager->index($node);
      }
    }
    else {
      if ($node = Node::load($data->id)) {
        $this->descendantManager->index($node);
      }
    }
  }

}

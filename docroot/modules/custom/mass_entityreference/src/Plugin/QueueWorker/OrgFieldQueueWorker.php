<?php

namespace Drupal\mass_entityreference\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set org field to author's org on all relevant nodes on cron runs.
 *
 * @QueueWorker(
 *   id = "mass_entityreference_org_field",
 *   title = @Translation("Org Field Queue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class OrgFieldQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Creates a new OrgFieldQueueWorker.
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
      $container->get('entity.manager')->getStorage('node')
    );
  }

  /**
   * Set org field to author's org on all relevant nodes.
   */
  public function processItem($data) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->nodeStorage->load($data->nid);
    if ($node instanceof NodeInterface && $node->hasField('field_state_organization_tax')) {

      // Get the node author's organization taxonomy term.
      $user = $node->getOwner();
      $org = $user->get('field_user_org');

      if ($org->first()) {
        $org_item = $org->first()->getValue();
        if (isset($org_item['target_id'])) {
          $org_id = $org_item['target_id'];
          $org_term = taxonomy_term_load($org_id);

          // Auto-Populate organization field.
          $time = $node->getChangedTime();
          $node->set('field_state_organization_tax', $org_term);
          $node->save();

          // Needs to be saved again to reset timestamp to its original value.
          $node->setChangedTime($time);
          $node->save();
        }
      }
    }
  }

}

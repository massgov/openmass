<?php

namespace Drupal\mass_utility\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued content items for revisions paring.
 *
 * @QueueWorker(
 *   id = "mass_utility_revisions_cleanup",
 *   title = @Translation("Cleanup revisions queued for deletion.")
 * )
 */
class RevisionCleanupQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Provides $rids variable.
    extract($data);

    // Removes the revision from the system if it's not a default revision.
    foreach ($rids as $rid) {
      try {
        $this->entityTypeManager->getStorage('node')->deleteRevision($rid);
      }
      catch (\Exception $e) {
        // Do nothing here. If the system cannot remove a revision for whatever
        // reason, it will simply move on to the next revision in the queue.
      }
    }

  }

}

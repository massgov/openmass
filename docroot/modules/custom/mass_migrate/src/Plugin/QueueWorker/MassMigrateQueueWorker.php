<?php

namespace Drupal\mass_migrate\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MassAutoParentsQueueWorker class.
 *
 * A worker plugin to consume items from "mass_auto_parents_queue"
 * and assign parent relationships.
 *
 * @QueueWorker(
 *   id = "mass_migrate_queue",
 *   title = @Translation("Mass Auto Parents Queue"),
 * )
 */
class MassMigrateQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('mass_migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Don't spam all the users with content update emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    try {
      $entity_id = $data['entity_id'];
      dump($entity_id);
      $uid = $data['uid'];
      $query = \Drupal::database()->select('migrate_map_service_details', 'mmsd');
      $query->fields('mmsd', ['destid1']);
      $query->condition('mmsd.sourceid1', (int) $entity_id);
      $result = $query->execute()->fetch();
      $node = $this->entityTypeManager->getStorage('node')->load(reset($result));
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      dump($node->id());
      dump($user->id());
      // check this error
      // An error occurred when assigning parent relationship for entity with data: @"entity_id":"76276","uid":"1831". Error message: The user has already flagged the entity with the flag.
      $flag_id = 'watch_content';

      $flag_service = \Drupal::service('flag');
      $flag = $flag_service->getFlagById($flag_id);

      // Flag an entity with a specific flag.
      $flag_service->flag($flag, $node, $user);
      $flag->save();

    }
    catch (\Exception $e) {
      $this->logger->warning("An error occurred when assigning parent relationship for entity with data: @data. Error message: {$e->getMessage()}", ['@data' => json_encode($data)]);
    }

  }

}

<?php

namespace Drupal\mass_migrate\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MassMigrateQueueWorker class.
 *
 * A worker plugin to consume items from "mass_migrate_queue"
 * and assign flags.
 *
 * @QueueWorker(
 *   id = "mass_migrate_queue",
 *   title = @Translation("Mass Migrate Flags Queue"),
 * )
 */
class MassMigrateQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelInterface|LoggerChannelFactoryInterface $logger;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flagService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, $database, FlagServiceInterface $flag_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('mass_migrate');
    $this->database = $database;
    $this->flagService = $flag_service;
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
      $container->get('database'),
      $container->get('flag'),
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
      $uid = $data['uid'];
      $query = $this->database->select('migrate_map_service_details', 'mmsd');
      $query->fields('mmsd', ['destid1']);
      $query->condition('mmsd.sourceid1', (int) $entity_id);
      $result = $query->execute()->fetch();
      $node = $this->entityTypeManager->getStorage('node')->load(reset($result));
      $user = $this->entityTypeManager->getStorage('user')->load($uid);

      $flag_id = 'watch_content';
      $flag = $this->flagService->getFlagById($flag_id);

      if ($flag && $node && $user) {
        if (!$flag->isFlagged($node, $user)) {
          // Flag an entity with a specific flag.
          $this->flagService->flag($flag, $node, $user);
          $flag->save();
        }
      }

    }
    catch (\Exception $e) {
      $this->logger->warning("An error occurred when assigning flag for entity with data: @data. Error message: {$e->getMessage()}", ['@data' => json_encode($data)]);
    }

  }

}

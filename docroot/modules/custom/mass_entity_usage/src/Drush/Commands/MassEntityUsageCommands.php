<?php

namespace Drupal\mass_entity_usage\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

final class MassEntityUsageCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityUsageQueueBatchManager $queueBatchManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $config_factory,
    protected Connection $database
  ) {
    parent::__construct();
  }

  /**
   * Recreate all entity usage statistics.
   *
   * @command mass-content:usage-regenerate
   * @aliases maur,mass-usage-regenerate
   * @option batch-size
   *   The --batch-size flag can be optionally used to
   *   specify the batch size, for example --batch-size=500.
   */
  public function recreate($options = ['batch-size' => 1000]) {
    $this->database->delete('queue_unique')->condition('name', 'entity_usage_tracker')->execute();
    $this->queueBatchManager->populateQueue($options['batch-size']);
    drush_backend_batch_process();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mass_entity_usage\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mass_entity_usage\UsageTrackingBlocker;

/**
 * General hook implementations for Mass Entity Usage.
 */
final class MassEntityUsageHooks {

  /**
   * The usage tracking blocker.
   */
  private readonly UsageTrackingBlocker $trackingBlocker;

  /**
   * Constructs general Mass Entity Usage hook implementations.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->trackingBlocker = new UsageTrackingBlocker($database, $entity_type_manager);
  }

  /**
   * Blocks tracking of entities with an unpublished/trash node source.
   */
  #[Hook('entity_usage_block_tracking')]
  public function entityUsageBlockTracking($target_id, $target_type, $source_id, $source_type, $source_langcode, $source_vid, $method, $field_name, $count): bool {
    return !$this->trackingBlocker->check($source_type, $source_vid);
  }

  /**
   * Removes entity usage queues from cron processing.
   */
  #[Hook('queue_info_alter')]
  public function queueInfoAlter(array &$queues): void {
    unset($queues['entity_usage_regenerate_queue']['cron']);
    unset($queues['entity_usage_tracker']['cron']);
  }

}

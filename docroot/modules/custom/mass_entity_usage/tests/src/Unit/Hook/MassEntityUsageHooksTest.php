<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_entity_usage\Unit\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_entity_usage\Hook\MassEntityUsageHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Mass Entity Usage hook implementations.
 *
 * @group mass_entity_usage
 */
final class MassEntityUsageHooksTest extends UnitTestCase {

  /**
   * Tests entity usage queues are removed from cron processing.
   */
  public function testQueueInfoAlterRemovesEntityUsageCronProcessing(): void {
    $hooks = new MassEntityUsageHooks(
      $this->createMock(Connection::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $queues = [
      'entity_usage_regenerate_queue' => [
        'worker callback' => 'test_regenerate',
        'cron' => ['time' => 60],
      ],
      'entity_usage_tracker' => [
        'worker callback' => 'test_tracker',
        'cron' => ['time' => 60],
      ],
      'other_queue' => [
        'worker callback' => 'test_other',
        'cron' => ['time' => 60],
      ],
    ];

    $hooks->queueInfoAlter($queues);

    $this->assertArrayNotHasKey('cron', $queues['entity_usage_regenerate_queue']);
    $this->assertArrayNotHasKey('cron', $queues['entity_usage_tracker']);
    $this->assertSame(['time' => 60], $queues['other_queue']['cron']);
  }

  /**
   * Tests missing entity usage queues are ignored.
   */
  public function testQueueInfoAlterIgnoresMissingEntityUsageQueues(): void {
    $hooks = new MassEntityUsageHooks(
      $this->createMock(Connection::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $queues = [
      'other_queue' => [
        'worker callback' => 'test_other',
        'cron' => ['time' => 60],
      ],
    ];

    $hooks->queueInfoAlter($queues);

    $this->assertSame(['time' => 60], $queues['other_queue']['cron']);
  }

}

<?php

namespace Drupal\Tests\mass_entityreference\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies mass_entityreference queue workers are cron-safe.
 */
class QueueWorkerCronDefinitionTest extends MassExistingSiteBase {

  /**
   * Ensures custom queue workers have cron time set on the instantiated plugin.
   */
  public function testQueueWorkersHaveCronTime() : void {
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');

    $custom_cron_queue_workers = [];
    foreach ($queue_worker_manager->getDefinitions() as $id => $definition) {
      // Only test our custom modules' queue workers to avoid brittleness with
      // contrib/core plugins we don't control.
      if (($definition['provider'] ?? '') === '' || strpos($definition['provider'], 'mass_') !== 0) {
        continue;
      }

      // Only test queue workers that declare cron settings.
      if (!isset($definition['cron'])) {
        continue;
      }

      $custom_cron_queue_workers[$id] = $definition['provider'];
    }

    $this->assertNotEmpty($custom_cron_queue_workers, 'Expected at least one custom cron queue worker to be discovered.');

    foreach (array_keys($custom_cron_queue_workers) as $id) {
      $worker = $queue_worker_manager->createInstance($id);
      $definition = $worker->getPluginDefinition();

      $this->assertArrayHasKey('cron', $definition, sprintf('Queue worker %s must define cron settings.', $id));
      $this->assertIsArray($definition['cron'], sprintf('Queue worker %s cron setting must be an array.', $id));
      $this->assertArrayHasKey('time', $definition['cron'], sprintf('Queue worker %s must define cron.time.', $id));
      $this->assertNotNull($definition['cron']['time'], sprintf('Queue worker %s cron.time must not be NULL.', $id));
      $this->assertIsInt($definition['cron']['time'], sprintf('Queue worker %s cron.time must be an int.', $id));
      $this->assertGreaterThan(0, $definition['cron']['time'], sprintf('Queue worker %s cron.time must be > 0.', $id));
    }
  }

}

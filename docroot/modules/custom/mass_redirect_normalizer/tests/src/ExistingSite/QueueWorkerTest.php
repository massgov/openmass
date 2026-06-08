<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests queue worker processing, presave hook integration, and change log writes.
 *
 * Covers: presave enqueue, worker normalization, env re-entry guard,
 * change log table writes for successes and failures.
 *
 * @group existing-site
 */
class QueueWorkerTest extends MassExistingSiteBase {

  use RedirectNormalizerTestTrait;

  /**
   * Tests presave hook normalizes node rich-text links on save.
   */
  public function testPresaveHookNormalizesNodeBodyOnSave(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Normalize me</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Trigger presave enqueue on node save.
    $sourceNode->save();
    $this->drainNormalizationQueue();

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($sourceNode->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $body = (string) $reloaded->get('body')->value;
    $this->assertStringContainsString($target->toUrl()->toString(), $body);
    $this->assertStringContainsString('data-entity-type="node"', $body);
  }

  /**
   * Tests presave enqueues the node and the queue worker rewrites redirect links.
   */
  public function testPresaveEnqueueThenWorkerNormalizesBody(): void {
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $this->purgeNormalizationQueue();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Presave queue</a></p>',
        'format' => 'full_html',
      ],
    ]);
    // Second save: presave runs with a real nid so the enqueuer can queue work.
    $sourceNode->save();

    $this->assertGreaterThan(0, $queue->numberOfItems(), 'Presave should enqueue normalization work.');

    $worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $claimed = $queue->claimItem();
    $this->assertNotFalse($claimed);
    $this->assertSame('node', $claimed->data['entity_type']);
    $this->assertSame((int) $sourceNode->id(), (int) $claimed->data['entity_id']);
    $this->assertSame('presave', $claimed->data['source']);
    $worker->processItem($claimed->data);
    $queue->deleteItem($claimed);

    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }

    $this->assertSame(0, $queue->numberOfItems());

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($sourceNode->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $body = (string) $reloaded->get('body')->value;
    $this->assertStringContainsString($target->toUrl()->toString(), $body);
    $this->assertStringContainsString('data-entity-type="node"', $body);
  }

  /**
   * Tests queue-processing env suppresses presave enqueue (worker re-entry guard).
   */
  public function testQueueProcessingEnvSuppressesPresaveEnqueue(): void {
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $this->purgeNormalizationQueue();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Env guard</a></p>',
        'format' => 'full_html',
      ],
    ]);
    $this->purgeNormalizationQueue();

    $countBeforeGuardedSave = $queue->numberOfItems();
    $_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING'] = TRUE;
    try {
      $sourceNode->save();
      $this->assertSame(
        $countBeforeGuardedSave,
        $queue->numberOfItems(),
        'Presave must not enqueue when MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING is set.'
      );
    }
    finally {
      unset($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING']);
    }

    $countBeforeUnguardedSave = $queue->numberOfItems();
    $sourceNode->save();
    $this->assertGreaterThan(
      $countBeforeUnguardedSave,
      $queue->numberOfItems(),
      'Presave should enqueue when queue processing env is not set.'
    );

    $this->purgeNormalizationQueue();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('node', (int) $sourceNode->id(), 'presave');
    $this->assertSame(1, $queue->numberOfItems());

    $worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $claimed = $queue->claimItem();
    $this->assertNotFalse($claimed);
    $worker->processItem($claimed->data);
    $queue->deleteItem($claimed);

    $this->assertFalse(
      !empty($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING']),
      'Queue worker must unset MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING after processing.'
    );
    $this->assertSame(
      0,
      $queue->numberOfItems(),
      'Queue worker saves must not re-enqueue via presave while processing.'
    );
  }

  /**
   * Tests queue worker writes changed rows into change log table.
   */
  public function testQueueWorkerWritesChangedRowsToChangeLogTable(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Ensure source is still present in storage after presave may have changed it.
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $redirectMarkup = '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>';
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirectMarkup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('node', $nid, 'drush');

    $this->drainNormalizationQueue();

    $count = (int) \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertGreaterThan(0, $count);

    $status = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', $nid)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $this->assertSame('succeeded', $status);
  }

  /**
   * Tests queue worker logs failed rows when paragraph host update fails.
   */
  public function testQueueWorkerLogsFailureWhenParagraphHostUpdateFails(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    [$node, $paragraphId] = $this->createHowToWithMethodParagraph();
    unset($node);

    $throwingManager = new class(
      \Drupal::service('mass_redirect_normalizer.resolver'),
      \Drupal::service('datetime.time'),
      \Drupal::entityTypeManager(),
      \Drupal::database(),
    ) extends RedirectLinkNormalizationManager {
      public function normalizeEntity(ContentEntityInterface $entity, bool $save = TRUE, bool $dryRun = FALSE): array {
        throw new \RuntimeException('Failed to update host node 1 to reference normalized paragraph revision 2.');
      }
    };
    \Drupal::getContainer()->set('mass_redirect_normalizer.manager', $throwingManager);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('paragraph', $paragraphId, 'drush');
    $this->drainNormalizationQueue();

    $status = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status', 'error_message'])
      ->condition('entity_type', 'paragraph')
      ->condition('entity_id', $paragraphId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($status);
    $this->assertSame('failed', $status['status']);
    $this->assertStringContainsString('Failed to update host node', (string) $status['error_message']);
  }

  /**
   * Tests change log service logs succeeded and failed rows with status field.
   */
  public function testChangeLogStatusColumnForSuccessAndFailure(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');

    $service->logChanges('node', 55501, 'page', 'drush', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => '<a href="/old">old</a>',
        'after' => '<a href="/new">new</a>',
      ],
    ]);
    $service->logFailure('node', 55502, 'page', 'drush', 'Example failure message.');

    $succeeded = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status', 'field_name', 'before_value'])
      ->condition('entity_id', 55501)
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($succeeded);
    $this->assertSame('succeeded', $succeeded['status']);
    $this->assertSame('body', $succeeded['field_name']);
    $this->assertStringContainsString('/old', (string) $succeeded['before_value']);

    $failed = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status', 'error_message'])
      ->condition('entity_id', 55502)
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($failed);
    $this->assertSame('failed', $failed['status']);
    $this->assertSame('Example failure message.', $failed['error_message']);
  }

}

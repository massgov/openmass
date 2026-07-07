<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_entity_usage\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mass_entity_usage\EntityUsageQueueBatchManager
 * @group mass_entity_usage
 */
class EntityUsageQueueBatchManagerTest extends UnitTestCase {

  /**
   * In-memory state backing store.
   *
   * @var array<string, mixed>
   */
  private array $stateStore = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->stateStore = [];
  }

  /**
   * @covers ::hasInterruptedProgress
   */
  public function testHasInterruptedProgressDetectsIncompleteRun(): void {
    $manager = $this->createManager();
    $this->stateStore[EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY] = 'run-1';
    $this->stateStore['mass_entity_usage.queue_progress.node'] = [
      'run_id' => 'run-1',
      'progress' => 100,
      'total' => 200,
      'completed' => FALSE,
    ];

    $this->assertTrue($manager->hasInterruptedProgress());
  }

  /**
   * @covers ::hasInterruptedProgress
   */
  public function testHasInterruptedProgressIgnoresStaleRunId(): void {
    $manager = $this->createManager();
    $this->stateStore[EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY] = 'run-new';
    $this->stateStore['mass_entity_usage.queue_progress.paragraph'] = [
      'run_id' => 'run-old',
      'progress' => 500,
      'total' => 1000,
      'completed' => FALSE,
    ];

    $this->assertFalse($manager->hasInterruptedProgress());
  }

  /**
   * @covers ::hasInterruptedProgress
   * @covers ::syncEnqueueRunIdFromProgress
   */
  public function testHasInterruptedProgressRecoversRunIdFromProgress(): void {
    $manager = $this->createManager();
    $this->stateStore['mass_entity_usage.queue_progress.paragraph'] = [
      'run_id' => 'run-recovered',
      'progress' => 50,
      'total' => 100,
      'completed' => FALSE,
    ];

    $this->assertTrue($manager->hasInterruptedProgress());
    $this->assertSame('run-recovered', $manager->getEnqueueRunId());
  }

  /**
   * @covers ::isEnqueueCompleted
   * @covers ::generateBatch
   */
  public function testGenerateBatchSkipsCompletedEntityTypes(): void {
    $manager = $this->createManager();
    $this->stateStore[EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY] = 'run-1';
    $this->stateStore['mass_entity_usage.queue_progress.node'] = [
      'run_id' => 'run-1',
      'progress' => 10,
      'total' => 10,
      'completed' => TRUE,
    ];
    $this->stateStore['mass_entity_usage.queue_progress.paragraph'] = [
      'run_id' => 'run-1',
      'progress' => 5,
      'total' => 20,
      'completed' => FALSE,
    ];

    $batch = $manager->generateBatch();
    $entity_types = array_map(static fn(array $operation): string => $operation[1][0], $batch['operations']);

    $this->assertSame(['paragraph'], $entity_types);
  }

  /**
   * @covers ::prepareResume
   */
  public function testPrepareResumeAttachesMissingRunIdWhenRunIdAlreadySet(): void {
    $manager = $this->createManager();
    $this->stateStore[EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY] = 'run-1';
    $this->stateStore['mass_entity_usage.queue_progress.paragraph'] = [
      'progress' => 5,
      'total' => 20,
      'completed' => FALSE,
    ];

    $manager->prepareResume();

    $progress = $this->stateStore['mass_entity_usage.queue_progress.paragraph'];
    $this->assertSame('run-1', $progress['run_id']);
  }

  /**
   * @covers ::getProgressSummary
   */
  public function testGetProgressSummaryReportsPerTypeStatus(): void {
    $manager = $this->createManager();
    $this->stateStore[EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY] = 'run-1';
    $this->stateStore['mass_entity_usage.queue_progress.node'] = [
      'run_id' => 'run-1',
      'progress' => 10,
      'total' => 10,
      'completed' => TRUE,
    ];
    $this->stateStore['mass_entity_usage.queue_progress.paragraph'] = [
      'run_id' => 'run-1',
      'progress' => 5,
      'total' => 20,
      'completed' => FALSE,
    ];

    $this->assertSame([
      'node' => 'completed 10/10',
      'paragraph' => 'in progress 5/20',
    ], $manager->getProgressSummary());
  }

  /**
   * @covers ::clearTrackedProgress
   */
  public function testClearTrackedProgressRemovesProgressAndCompletionKeys(): void {
    $progress_keys = [
      'mass_entity_usage.queue_progress.node' => ['progress' => 1],
      'mass_entity_usage.queue_progress.paragraph' => ['progress' => 2],
      EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY => 12345,
    ];
    $this->stateStore = $progress_keys;
    $manager = $this->createManager($progress_keys);

    $manager->clearTrackedProgress();

    $this->assertArrayNotHasKey('mass_entity_usage.queue_progress.node', $this->stateStore);
    $this->assertArrayNotHasKey('mass_entity_usage.queue_progress.paragraph', $this->stateStore);
    $this->assertArrayNotHasKey(EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY, $this->stateStore);
  }

  /**
   * Builds a batch manager with mocked services.
   *
   * @param array<string, mixed> $progress_keys
   *   Progress state keys returned by the database prefix query.
   */
  private function createManager(array $progress_keys = []): EntityUsageQueueBatchManager {
    $node_type = $this->createMock(ContentEntityTypeInterface::class);
    $node_type->method('entityClassImplements')
      ->willReturnCallback(static fn(string $class): bool => $class === '\Drupal\Core\Entity\ContentEntityInterface');

    $paragraph_type = $this->createMock(ContentEntityTypeInterface::class);
    $paragraph_type->method('entityClassImplements')
      ->willReturnCallback(static fn(string $class): bool => $class === '\Drupal\Core\Entity\ContentEntityInterface');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'node' => $node_type,
      'paragraph' => $paragraph_type,
    ]);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('track_enabled_source_entity_types')
      ->willReturn(['node', 'paragraph']);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('entity_usage.settings')
      ->willReturn($config);

    $state = $this->createStateMock();
    $database = $this->createDatabaseMock($progress_keys);

    $string_translation = $this->createMock(TranslationInterface::class);
    $string_translation->method('translateString')->willReturnArgument(0);

    return new EntityUsageQueueBatchManager(
      $entity_type_manager,
      $string_translation,
      $config_factory,
      $state,
      $database,
    );
  }

  /**
   * @param array<string, mixed> $progress_keys
   */
  private function createDatabaseMock(array $progress_keys = []): Connection {
    $database = $this->createMock(Connection::class);
    $select = $this->createMock(SelectInterface::class);
    $statement = $this->createMock(StatementInterface::class);
    $database->method('select')->willReturn($select);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $statement->method('fetchCol')->willReturn(array_keys($progress_keys));
    $select->method('execute')->willReturn($statement);
    $database->method('escapeLike')->willReturnArgument(0);
    return $database;
  }

  private function createStateMock(): StateInterface {
    $state = $this->createMock(StateInterface::class);
    $store = &$this->stateStore;
    $state->method('get')->willReturnCallback(function (string $key, $default = NULL) use (&$store) {
      return $store[$key] ?? $default;
    });
    $state->method('set')->willReturnCallback(function (string $key, $value) use (&$store): void {
      $store[$key] = $value;
    });
    $state->method('delete')->willReturnCallback(function (string $key) use (&$store): void {
      unset($store[$key]);
    });
    return $state;
  }

}

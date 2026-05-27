<?php

namespace Drupal\mass_redirect_normalizer\Plugin\QueueWorker;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mass_redirect_normalizer\RedirectLinkChangeLog;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationEligibility;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued redirect-link normalization jobs.
 *
 * @QueueWorker(
 *   id = "mass_redirect_normalizer_link_normalization",
 *   title = @Translation("Redirect link normalization"),
 * )
 */
class RedirectLinkNormalizationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationManager $normalizerManager,
    protected RedirectLinkNormalizationEligibility $eligibility,
    protected RedirectLinkChangeLog $changeLog,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('mass_redirect_normalizer.manager'),
      $container->get('mass_redirect_normalizer.eligibility'),
      $container->get('mass_redirect_normalizer.change_log'),
      $container->get('logger.channel.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      return;
    }

    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING'] = TRUE;
    $source = (string) ($data['source'] ?? 'drush');

    try {
      $pairs = $this->expandQueuePayload($data);
      $loadedByTypeAndId = $this->loadEntitiesForPairs($pairs);

      foreach ($pairs as $pair) {
        [$entityType, $entityId] = $pair;
        $entity = $loadedByTypeAndId[$entityType][$entityId] ?? NULL;
        try {
          $this->processEntity($entityType, $entityId, $source, $entity);
        }
        catch (\Throwable $exception) {
          $this->logger->error('Redirect link normalization failed for @type:@id: @message', [
            '@type' => $entityType,
            '@id' => $entityId,
            '@message' => $exception->getMessage(),
          ]);
          $this->changeLog->logFailure(
            $entityType,
            $entityId,
            $entity instanceof ContentEntityInterface ? (string) $entity->bundle() : '',
            $source,
            $exception->getMessage(),
          );
        }
      }
    }
    finally {
      unset($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING']);
    }
  }

  /**
   * Processes a single queued entity.
   */
  private function processEntity(
    string $entityType,
    int $entityId,
    string $source,
    ?ContentEntityInterface $entity = NULL,
  ): void {
    if (!in_array($entityType, RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES, TRUE) || $entityId <= 0) {
      return;
    }
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (!$this->eligibility->isEligible($entityType, $entity)) {
      return;
    }

    $result = $this->normalizerManager->normalizeEntity($entity, TRUE);
    if (!empty($result['changed']) && !empty($result['changes']) && is_array($result['changes'])) {
      $this->changeLog->logChanges($entityType, $entityId, (string) $entity->bundle(), $source, $result['changes']);
    }
  }

  /**
   * Accepts single-entity or bulk payloads.
   *
   * @return array<int, array{0:string,1:int}>
   *   Entity type and ID tuples.
   */
  private function expandQueuePayload(array $data): array {
    if (isset($data['entities']) && is_array($data['entities'])) {
      $out = [];
      foreach ($data['entities'] as $row) {
        if (!is_array($row)) {
          continue;
        }
        $entityType = (string) ($row['entity_type'] ?? '');
        $entityId = (int) ($row['entity_id'] ?? 0);
        if ($entityType !== '' && $entityId > 0) {
          $out[] = [$entityType, $entityId];
        }
      }
      return $out;
    }

    $entityType = (string) ($data['entity_type'] ?? '');
    $entityId = (int) ($data['entity_id'] ?? 0);
    if ($entityType === '' || $entityId <= 0) {
      return [];
    }
    return [[$entityType, $entityId]];
  }

  /**
   * Batch-loads all entities referenced by queue payload tuples.
   *
   * @param array<int, array{0:string,1:int}> $pairs
   *   Entity type and ID tuples.
   *
   * @return array<string, array<int, \Drupal\Core\Entity\ContentEntityInterface>>
   *   Loaded content entities indexed by [entity_type][entity_id].
   */
  private function loadEntitiesForPairs(array $pairs): array {
    $idsByType = [];
    foreach ($pairs as [$entityType, $entityId]) {
      if (!in_array($entityType, RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES, TRUE) || $entityId <= 0) {
        continue;
      }
      $idsByType[$entityType][$entityId] = $entityId;
    }

    $loaded = [];
    foreach ($idsByType as $entityType => $ids) {
      $entities = $this->entityTypeManager
        ->getStorage($entityType)
        ->loadMultiple(array_values($ids));

      foreach ($entities as $id => $entity) {
        if ($entity instanceof ContentEntityInterface) {
          $loaded[$entityType][(int) $id] = $entity;
        }
      }
    }

    return $loaded;
  }

}

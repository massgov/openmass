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
      foreach ($this->expandQueuePayload($data) as $pair) {
        [$entityType, $entityId] = $pair;
        try {
          $this->processEntity($entityType, $entityId, $source);
        }
        catch (\Throwable $exception) {
          $this->logger->error('Redirect link normalization failed for @type:@id: @message', [
            '@type' => $entityType,
            '@id' => $entityId,
            '@message' => $exception->getMessage(),
          ]);
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
  private function processEntity(string $entityType, int $entityId, string $source): void {
    if (!in_array($entityType, RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES, TRUE) || $entityId <= 0) {
      return;
    }
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
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

}

<?php

namespace Drupal\mass_redirect_normalizer\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
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
    protected RedirectLinkQueueEnqueuer $enqueuer,
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
      $container->get('mass_redirect_normalizer.enqueuer'),
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

    $entityType = (string) ($data['entity_type'] ?? '');
    $entityId = (int) ($data['entity_id'] ?? 0);
    if (!in_array($entityType, ['node', 'paragraph'], TRUE) || $entityId <= 0) {
      return;
    }

    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    try {
      $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
      if (!$entity) {
        return;
      }

      if (!$this->eligibility->isEligible($entityType, $entity)) {
        return;
      }

      $this->normalizerManager->normalizeEntity($entity, TRUE);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Redirect link normalization failed for @type:@id: @message', [
        '@type' => $entityType,
        '@id' => $entityId,
        '@message' => $exception->getMessage(),
      ]);
    }
    finally {
      $this->enqueuer->clearPending($entityType, $entityId);
    }
  }

}

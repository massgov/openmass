<?php

namespace Drupal\mass_org_access\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mass_org_access\BackfillRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Backfills field_content_organization on one entity per queue item.
 *
 * The queued counterpart of `drush moab`. Each item carries a single
 * {entity_type, id}; this worker loads that entity and runs the exact same
 * per-entity logic the sync command uses (BackfillRunner::backfillEntity()),
 * so the two paths can never diverge. Drained manually with
 * `drush queue:run mass_org_access_backfill` — there is intentionally no cron
 * key, so it never runs unattended.
 *
 * @QueueWorker(
 *   id = "mass_org_access_backfill",
 *   title = @Translation("Mass Org Access: backfill Permission Groups")
 * )
 */
class BackfillQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Whether icon placeholders were already ensured this worker process.
   */
  private static bool $iconsEnsured = FALSE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BackfillRunner $backfillRunner,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('mass_org_access.backfill_runner'),
      $container->get('logger.factory')->get('mass_org_access'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Bulk saves must not fire mass_flagging "Watch" notification emails.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    // Self-heal the media-icon placeholders once per worker process, so a
    // queue:run after a fresh DB/file pull still resolves document thumbnails.
    if (!self::$iconsEnsured) {
      $this->backfillRunner->ensureMediaIconPlaceholders();
      self::$iconsEnsured = TRUE;
    }

    $entity_type = $data['entity_type'] ?? NULL;
    $id = $data['id'] ?? NULL;
    if (!$entity_type || !$id) {
      return;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
    // The entity may have been deleted between enqueue and processing.
    if (!$entity) {
      return;
    }

    // One bad entity (e.g. a media item whose source file is gone everywhere)
    // is logged and dropped — never re-thrown, which would requeue the item
    // and loop forever. backfillEntity() is idempotent, so re-runs are safe.
    try {
      $this->backfillRunner->backfillEntity($entity);
    }
    catch (\Throwable $e) {
      $this->logger->warning('SKIPPED @type:@id — @class: @message', [
        '@type' => $entity_type,
        '@id' => $id,
        '@class' => (new \ReflectionClass($e))->getShortName(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}

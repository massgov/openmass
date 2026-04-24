<?php

namespace Drupal\mass_org_access\Drush\Commands;

use Drupal\mass_org_access\BackfillBatchManager;
use Drupal\mass_org_access\OrgAccessChecker;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for mass_org_access.
 */
class MassOrgAccessCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly BackfillBatchManager $batchManager,
    private readonly OrgAccessChecker $orgAccessChecker,
  ) {
    parent::__construct();
  }

  /**
   * Populate field_content_organization on all existing nodes and media.
   *
   * Reads field_organizations on each entity, resolves the corresponding
   * user_organization taxonomy terms (including ancestor terms), and writes
   * them to field_content_organization. This is a one-time backfill for
   * content that existed before the mass_org_access module was enabled.
   *
   * @command mass-org-access:backfill
   * @aliases moab
   * @usage drush mass-org-access:backfill
   *   Backfill field_content_organization on all nodes and media.document.
   */
  public function backfill(): void {
    $this->batchManager->queueBackfill();
    drush_backend_batch_process();
  }

  /**
   * Dev helper: sync field_content_organization on first 100 nodes and 100
   * media.document entities and print IDs with resulting term assignments.
   *
   * @command mass-org-access:backfill-dev
   * @aliases moab-dev
   * @usage drush mass-org-access:backfill-dev
   *   Sync 100 nodes + 100 media, print IDs and assigned org term IDs.
   */
  public function backfillDev(): void {
    $entity_type_manager = \Drupal::entityTypeManager();

    $node_ids = array_values($entity_type_manager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 100)
      ->execute());

    $media_ids = array_values($entity_type_manager->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', 'document')
      ->range(0, 100)
      ->execute());

    $this->processAndPrint('node', $node_ids, $entity_type_manager);
    $this->processAndPrint('media', $media_ids, $entity_type_manager);

    $this->output()->writeln('');
    $this->output()->writeln(sprintf('<info>Done. Processed %d nodes, %d media.</info>', count($node_ids), count($media_ids)));
  }

  private function processAndPrint(string $entity_type_id, array $ids, $entity_type_manager): void {
    if (empty($ids)) {
      $this->output()->writeln(sprintf('<comment>No %s entities found.</comment>', $entity_type_id));
      return;
    }

    $this->output()->writeln('');
    $this->output()->writeln(sprintf('<info>--- %s (%d) ---</info>', strtoupper($entity_type_id), count($ids)));

    $storage = $entity_type_manager->getStorage($entity_type_id);
    foreach ($storage->loadMultiple($ids) as $entity) {
      $this->orgAccessChecker->syncContentOrganization($entity);
      $term_ids = $this->orgAccessChecker->getEntityOrgTids($entity);

      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(FALSE);
      }
      $entity->setSyncing(TRUE);
      $storage->save($entity);

      $term_str = $term_ids ? implode(', ', $term_ids) : '(none)';
      $this->output()->writeln(sprintf('  %s:%d  →  org TIDs: [%s]', $entity_type_id, $entity->id(), $term_str));
    }
  }

}

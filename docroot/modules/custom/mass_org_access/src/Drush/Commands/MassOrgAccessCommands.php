<?php

namespace Drupal\mass_org_access\Drush\Commands;

use Drupal\mass_org_access\BackfillRunner;
use Drupal\mass_org_access\OrgAccessChecker;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for mass_org_access.
 */
class MassOrgAccessCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly BackfillRunner $backfillRunner,
    private readonly OrgAccessChecker $orgAccessChecker,
  ) {
    parent::__construct();
  }

  /**
   * Populate field_content_organization on all supported nodes and media.
   *
   * Resumable: progress is persisted in State so that a Ctrl+C, crash, or
   * fresh invocation continues from the last processed entity ID instead
   * of starting over. Skips org_page (manually maintained source of truth).
   * Writes a timestamped progress line to a log file and the console after
   * every batch.
   *
   * @command mass-org-access:backfill
   * @aliases moab
   * @option reset
   *   Wipe stored progress and start from scratch.
   * @option log
   *   Stream-wrapper URI of the log file. Defaults to
   *   private://mass_org_access/backfill.log.
   * @usage drush mass-org-access:backfill
   *   Backfill (or resume) field_content_organization across all targets.
   * @usage drush mass-org-access:backfill --reset
   *   Discard previous progress and rescan everything.
   * @usage drush mass-org-access:backfill --log=private://moab.log
   *   Pick a custom log file location.
   */
  public function backfill(array $options = ['reset' => FALSE, 'log' => NULL]): void {
    $this->backfillRunner->run(
      $this->output(),
      $options['log'] ? (string) $options['log'] : NULL,
      (bool) $options['reset']
    );
  }

  /**
   * Dev helper to sync the first 100 nodes and 100 media.document entities.
   *
   * Prints entity IDs with the resulting field_content_organization term
   * assignments.
   *
   * @command mass-org-access:backfill-dev
   * @aliases moab-dev
   * @usage drush mass-org-access:backfill-dev
   *   Sync 100 nodes + 100 media, print IDs and assigned org term IDs.
   */
  public function backfillDev(): void {
    // Same bulk-save semantics as the real backfill: suppress mass_flagging
    // "Watch" notifications so resaving entities does not email watchers.
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

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
      $this->populateRevision($entity, $storage);
      $term_ids = $this->orgAccessChecker->getEntityOrgTids($entity);

      // Mirror the real backfill: also populate a forward (unpublished)
      // draft, since edit access checks the latest revision.
      $latest_vid = $storage->getLatestRevisionId($entity->id());
      $draft_str = '';
      if ($latest_vid && (int) $latest_vid !== (int) $entity->getRevisionId()) {
        $draft = $storage->loadRevision($latest_vid);
        if ($draft) {
          $this->populateRevision($draft, $storage);
          $draft_str = sprintf(' + draft TIDs: [%s]', implode(', ', $this->orgAccessChecker->getEntityOrgTids($draft)) ?: '(none)');
        }
      }

      $term_str = $term_ids ? implode(', ', $term_ids) : '(none)';
      $this->output()->writeln(sprintf('  %s:%d  →  org TIDs: [%s]%s', $entity_type_id, $entity->id(), $term_str, $draft_str));
    }
  }

  /**
   * Populates one revision's Permission Groups and saves it in place.
   */
  private function populateRevision($revision, $storage): void {
    $this->orgAccessChecker->populateOwnerGroupsFromOrganizations($revision);
    if (method_exists($revision, 'setNewRevision')) {
      $revision->setNewRevision(FALSE);
    }
    $revision->setSyncing(TRUE);
    $storage->save($revision);
  }

}

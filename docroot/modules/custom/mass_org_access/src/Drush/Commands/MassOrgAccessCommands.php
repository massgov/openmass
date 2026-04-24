<?php

namespace Drupal\mass_org_access\Drush\Commands;

use Drupal\mass_org_access\BackfillBatchManager;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for mass_org_access.
 */
class MassOrgAccessCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly BackfillBatchManager $batchManager,
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

}

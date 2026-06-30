<?php

namespace Drupal\mass_entity_usage\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Drush commands for mass entity usage management.
 */
final class MassEntityUsageCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityUsageQueueBatchManager $queueBatchManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $config_factory,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct();
  }

  /**
   * Recreate all entity usage statistics.
   *
   * @command mass-content:usage-regenerate
   * @aliases maur,mass-usage-regenerate
   * @option batch-size
   *   The --batch-size flag can be optionally used to
   *   specify the batch size, for example --batch-size=500.
   * @option force
   *   Bypass confirmation when starting a full rebuild.
   * @option reset
   *   Clear saved enqueue progress and start a full rebuild (with confirmation).
   *
   * If a previous run was interrupted, running this command again will resume
   * automatically with no prompt. If enqueue completed within 24 hours, the
   * command exits with next-step instructions. Otherwise it starts a new
   * enqueue without prompting. Use --reset (with confirmation) to force a
   * full rebuild; add --force or -y to skip the reset confirmation.
   *
   * @usage drush mass-content:usage-regenerate
   *   Resume an interrupted enqueue, exit if recently completed, or start anew.
   * @usage drush mass-content:usage-regenerate --reset
   *   Confirm, then clear saved progress and start a full rebuild.
   * @usage drush mass-content:usage-regenerate --reset --force
   *   Start a full rebuild without confirmation.
   */
  public function recreate($options = ['batch-size' => 1000, 'force' => FALSE, 'reset' => FALSE]) {
    if (!empty($options['reset'])) {
      $this->confirmFullRebuild((bool) $options['force']);
      $this->startFreshEnqueue('Starting a new full enqueue from scratch (--reset).');
    }
    elseif ($this->queueBatchManager->hasInterruptedProgress()) {
      $this->queueBatchManager->prepareResume();
      $this->logger()->notice('Resuming interrupted enqueue from saved progress.');
      foreach ($this->queueBatchManager->getProgressSummary() as $entity_type_id => $status) {
        $this->io()->writeln("  {$entity_type_id}: {$status}");
      }
    }
    elseif ($this->queueBatchManager->wasEnqueueCompletedRecently()) {
      $this->notifyEnqueueRecentlyCompleted();
      return;
    }
    elseif ($this->queueBatchManager->isAllEnqueueCompleted()) {
      $this->queueBatchManager->markEnqueueCompleted();
      $this->notifyEnqueueRecentlyCompleted();
      return;
    }
    else {
      $this->startFreshEnqueue('Starting a new full enqueue (completion flag expired or first run).');
    }

    if (!$this->queueBatchManager->populateQueue($options['batch-size'])) {
      $this->logger()->notice('All tracked entity types are already enqueued. Process the queue with: drush queue:run entity_usage_tracker');
      return;
    }
    drush_backend_batch_process();
  }

  /**
   * Clears enqueue state and prepares for a fresh run.
   */
  protected function startFreshEnqueue(string $message): void {
    $this->queueBatchManager->beginFreshEnqueueRun();
    $this->logger()->notice($message);
  }

  /**
   * Informs the operator that enqueue already finished recently.
   */
  protected function notifyEnqueueRecentlyCompleted(): void {
    $completed_at = $this->queueBatchManager->getEnqueueCompletedAt();
    $completed_label = $completed_at
      ? $this->dateFormatter->format($completed_at, 'custom', 'Y-m-d H:i:s T')
      : 'recently';

    $this->io()->writeln("Enqueue completed at {$completed_label} (within the last 24 hours).");
    $this->io()->writeln('Process queued work with: drush queue:run entity_usage_tracker');
    $this->io()->writeln('To start a new enqueue from scratch, run: drush mass-content:usage-regenerate --reset');
  }

  /**
   * Prompts before a destructive full rebuild.
   */
  protected function confirmFullRebuild(bool $force): void {
    if ($force || $this->input()->getOption('yes')) {
      return;
    }

    $this->io()->warning(
      'This will start a FULL entity usage rebuild: clear saved progress, '
      . 'wipe existing usage records per entity type, and re-enqueue all entities. '
      . 'Resume is only automatic when unfinished progress exists in state.'
    );

    if (!$this->input()->isInteractive()) {
      throw new UserAbortException('Full rebuild aborted in non-interactive mode. Pass --force or -y to confirm.');
    }

    if (!$this->io()->confirm('Start a full rebuild?', FALSE)) {
      throw new UserAbortException();
    }
  }

}

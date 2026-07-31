<?php

namespace Drupal\trashbin\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

final class TrashbinCommands extends DrushCommands {

  use AutowireTrait;

  const TRASHBIN_PURGE = 'trashbin:purge';

  public function __construct(
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time,
    private TrashbinPurgeCandidateQuery $purgeCandidateQuery,
  ) {
    parent::__construct();
  }

  /**
   * Delete content entities in Trash; when --days-ago=0, delete all trashed items; when >0, delete only those older than N days.
   */
  #[CLI\Command(name: self::TRASHBIN_PURGE, aliases: [])]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type to purge')]
  #[CLI\Option(name: 'max', description: 'Maximum number of entities to delete.')]
  #[CLI\Option(name: 'days-ago', description: 'Number of days that the item must be unchanged in the trashbin.')]
  #[CLI\Usage(name: 'drush --simulate trashbin:purge node', description: 'Get a report of what would be purged.')]
  public function purge($entity_type, $options = ['max' => 1000, 'days-ago' => 180]) {
    // For a destructive command a malformed option must fail loudly instead
    // of silently coercing to 0 — which is the delete-everything mode for
    // days-ago and a no-op for max.
    if (!ctype_digit((string) $options['days-ago'])) {
      throw new \InvalidArgumentException(sprintf('--days-ago must be a non-negative integer, got "%s".', $options['days-ago']));
    }
    if (!ctype_digit((string) $options['max'])) {
      throw new \InvalidArgumentException(sprintf('--max must be a non-negative integer, got "%s".', $options['max']));
    }
    $days_ago = (int) $options['days-ago'];

    // Capture command start time to avoid racing with edits during execution.
    $startedAt = $this->time->getCurrentTime();
    $cutoff = $days_ago > 0 ? strtotime($days_ago . ' days ago', $startedAt) : $startedAt;

    $storage = $this->etm->getStorage($entity_type);

    $ids = $this->purgeCandidateQuery->getCandidateIds(
      $entity_type,
      (int) $options['max'],
      $cutoff
    );

    $this->logger()->notice('Found {count} candidates to delete.', ['count' => count($ids)]);

    $deleted = 0;
    $simulated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($ids as $id) {
      $entity = $storage->load($id);
      if (!$entity) {
        $this->logger()->notice('Skipping ID={id}; entity no longer exists.', ['id' => $id]);
        $skipped++;
        continue;
      }

      // Re-check right before delete: via SQL against the stable cutoff, and
      // via the entity API — the loaded entity is the authority on the
      // current moderation state (stale rows from old workflows can disagree
      // with the SQL view of it).
      if (!$this->purgeCandidateQuery->isEntityEligible($entity_type, $id, $cutoff)
        || !$entity->hasField('moderation_state')
        || $entity->get('moderation_state')->value !== 'trash') {
        $this->logger()->notice('Skipping "{title}". ID={id}; no longer in trash or too recent.', [
          'title' => $entity->label(),
          'id' => $entity->id(),
        ]);
        $skipped++;
        continue;
      }

      if ($this->getConfig()->simulate()) {
        $this->logger()->notice('Simulated delete of "{title}". ID={id}, {url}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
          'url' => $entity->hasLinkTemplate('canonical') ? $entity->toUrl('canonical', ['absolute' => TRUE])->toString() : 'n/a',
        ]);
        $simulated++;
        $storage->resetCache([$id]);
        continue;
      }

      try {
        $entity->delete();
        $deleted++;
        $this->logger()->notice('Deleted "{title}". ID={id}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
        ]);
      }
      catch (\Throwable $e) {
        // One entity failing must not abort the batch: with deterministic
        // oldest-first ordering it would wedge the purge at the same spot on
        // every run.
        $failed++;
        $this->logger()->error('Failed to delete "{title}". ID={id}: {message}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
          'message' => $e->getMessage(),
        ]);
      }
    }

    $this->logger()->notice('Purge finished: deleted {deleted}, simulated {simulated}, skipped {skipped}, failed {failed}.', [
      'deleted' => $deleted,
      'simulated' => $simulated,
      'skipped' => $skipped,
      'failed' => $failed,
    ]);

    if ($failed) {
      throw new \RuntimeException(sprintf('Failed to delete %d %s entities; see the log for details.', $failed, $entity_type));
    }
  }

}

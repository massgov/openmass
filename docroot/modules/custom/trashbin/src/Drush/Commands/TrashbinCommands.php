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

  /**
   * Upper bound for --days-ago (100 years).
   *
   * Digits-only input larger than this would overflow the cutoff arithmetic
   * and must be rejected rather than silently misbehave.
   */
  const DAYS_AGO_MAX = 36500;

  public function __construct(
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time,
    private TrashbinPurgeCandidateQuery $purgeCandidateQuery,
  ) {
    parent::__construct();
  }

  /**
   * Permanently delete content entities that are in the Trash.
   */
  #[CLI\Command(name: self::TRASHBIN_PURGE, aliases: [])]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type to purge')]
  #[CLI\Option(name: 'max', description: 'Maximum number of entities to delete. 0 deletes nothing.')]
  #[CLI\Option(name: 'days-ago', description: 'Only delete items unchanged in the trashbin for this many days. 0 deletes all trashed items that existed before the command started.')]
  #[CLI\Usage(name: 'drush --simulate trashbin:purge node', description: 'Get a report of what would be purged.')]
  public function purge($entity_type, $options = ['max' => 1000, 'days-ago' => 180]) {
    // For a destructive command a malformed option must fail loudly instead
    // of silently coercing to 0 — which is the delete-everything mode for
    // days-ago and a no-op for max.
    $days_ago_raw = $options['days-ago'] ?? 180;
    $max_raw = $options['max'] ?? 1000;
    if (is_bool($days_ago_raw) || !ctype_digit((string) $days_ago_raw)) {
      throw new \InvalidArgumentException(sprintf('--days-ago must be a non-negative integer, got "%s".', var_export($days_ago_raw, TRUE)));
    }
    if (is_bool($max_raw) || !ctype_digit((string) $max_raw)) {
      throw new \InvalidArgumentException(sprintf('--max must be a non-negative integer, got "%s".', var_export($max_raw, TRUE)));
    }
    $days_ago = (int) $days_ago_raw;
    if ($days_ago > self::DAYS_AGO_MAX) {
      throw new \InvalidArgumentException(sprintf('--days-ago must not exceed %d, got "%s".', self::DAYS_AGO_MAX, $days_ago_raw));
    }

    // Capture command start time to avoid racing with edits during execution.
    $startedAt = $this->time->getCurrentTime();
    $cutoff = $days_ago > 0 ? $startedAt - $days_ago * 86400 : $startedAt;

    $storage = $this->etm->getStorage($entity_type);

    $ids = $this->purgeCandidateQuery->getCandidateIds(
      $entity_type,
      (int) $max_raw,
      $cutoff
    );

    $this->logger()->notice('Found {count} candidates to delete.', ['count' => count($ids)]);

    $shadowed = $this->purgeCandidateQuery->countShadowedTrashRows($entity_type);
    if ($shadowed) {
      $this->logger()->notice('{count} {type} trash records are shadowed by a newer moderation record and are never purged.', [
        'count' => $shadowed,
        'type' => $entity_type,
      ]);
    }

    $deleted = 0;
    $simulated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($ids as $id) {
      try {
        $entity = $storage->load($id);
        if (!$entity) {
          $this->logger()->notice('Skipping ID={id}; entity no longer exists.', ['id' => $id]);
          $skipped++;
          continue;
        }

        if (!$this->purgeCandidateQuery->isEntityEligible($entity_type, $id, $cutoff)) {
          $this->logger()->notice('Skipping "{title}". ID={id}; no longer in trash or too recent.', [
            'title' => $entity->label(),
            'id' => $entity->id(),
          ]);
          $skipped++;
          $storage->resetCache([$id]);
          continue;
        }

        // The loaded entity is the authority on the current moderation
        // state: stale rows from old workflows can disagree with SQL. A
        // warning here means the moderation data itself needs cleanup.
        $state = $entity->hasField('moderation_state') ? $entity->get('moderation_state')->value : NULL;
        if ($state !== 'trash') {
          $this->logger()->warning('Skipping "{title}". ID={id}; stale moderation data or concurrent modification: SQL reports trash but the entity reports "{state}".', [
            'title' => $entity->label(),
            'id' => $entity->id(),
            'state' => $state ?? 'none',
          ]);
          $skipped++;
          $storage->resetCache([$id]);
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

        $entity->delete();
        $deleted++;
        $this->logger()->notice('Deleted "{title}". ID={id}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
        ]);
      }
      catch (\Exception $e) {
        // One entity failing must not abort the batch: with deterministic
        // oldest-first ordering it would wedge the purge at the same spot
        // on every run. \Error is deliberately NOT caught: core storage
        // only rolls its delete transaction back for \Exception, so an
        // \Error must abort the run rather than continue on top of an
        // abnormally unwound transaction.
        $failed++;
        $previous = $e->getPrevious();
        $this->logger()->error('Failed to purge entity ID={id}: {class}: {message}', [
          'id' => $id,
          'class' => get_class($e) . ($previous ? ' (' . get_class($previous) . ')' : ''),
          'message' => $e->getMessage(),
        ]);
        $storage->resetCache([$id]);
      }
    }

    $this->logger()->notice('Purge finished: deleted {deleted}, simulated {simulated}, skipped {skipped}, failed {failed}.', [
      'deleted' => $deleted,
      'simulated' => $simulated,
      'skipped' => $skipped,
      'failed' => $failed,
    ]);

    if ($ids && !$deleted && !$simulated && !$failed) {
      $this->logger()->warning('Purge made no progress: all {count} candidates were skipped.', ['count' => count($ids)]);
    }

    if ($failed) {
      throw new \RuntimeException(sprintf('Failed to purge %d %s entities; see the log for details.', $failed, $entity_type));
    }
  }

}

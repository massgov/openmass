<?php

namespace Drupal\trashbin\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

final class TrashbinCommands extends DrushCommands {

  use AutowireTrait;

  const TRASHBIN_PURGE = 'trashbin:purge';

  protected function __construct(
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
    // Capture command start time to avoid racing with edits during execution.
    $startedAt = $this->time->getCurrentTime();
    $maximum = strtotime($options['days-ago'] . ' days ago', $startedAt);

    $cutoff = ((int) $options['days-ago'] > 0) ? $maximum : $startedAt;

    $storage = $this->etm->getStorage($entity_type);
    $definition = $storage->getEntityType();

    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    if (!$base_table) {
      $this->logger()->error('Entity type {type} does not have a base/data table and cannot be purged.', ['type' => $entity_type]);
      return;
    }

    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');

    if (!$id_key || !$rev_key) {
      $this->logger()->error('Entity type {type} must be revisionable to use trashbin purge (missing id/revision keys).', ['type' => $entity_type]);
      return;
    }

    $ids = $this->purgeCandidateQuery->getCandidateIds(
      $entity_type,
      (int) $options['max'],
      $cutoff
    );

    $this->logger()->notice('Found {count} entities to delete.', ['count' => count($ids)]);

    if (!$ids) {
      return;
    }

    $entities = $storage->loadMultiple($ids);
    foreach ($entities as $entity) {
      if (Drush::simulate()) {
        $this->logger()->notice('Simulated delete of "{title}". ID={id}, {url}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
          'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
        ]);
      }
      else {
        $this->logger()->notice('Start delete of "{title}". ID={id}', [
          'title' => $entity->label(),
          'id' => $entity->id(),
        ]);
        $entity->delete();
      }
    }
  }

}

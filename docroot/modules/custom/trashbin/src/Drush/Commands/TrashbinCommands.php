<?php

namespace Drupal\trashbin\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  ) {
  }

  /**
   * Delete content entities that have been in the bin for more than n days.
   */
  #[CLI\Command(name: self::TRASHBIN_PURGE, aliases: [])]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type to purge')]
  #[CLI\Option(name: 'max', description: 'Maximum number of entities to delete.')]
  #[CLI\Option(name: 'days-ago', description: 'Number of days that the item must be unchanged in the trashbin.')]
  #[CLI\Usage(name: 'drush --simulate trashbin:purge node', description: 'Get a report of what would be purged.')]
  public function purge($entity_type, $options = ['max' => 1000, 'days-ago' => 180]) {
    $maximum = strtotime($options['days-ago'] . ' days ago', $this->time->getCurrentTime());

    // Validate entity type and get storage/definition.
    $storage = $this->etm->getStorage($entity_type);
    $definition = $storage->getEntityType();

    // Resolve table/keys.
    $base_table = method_exists($storage, 'getBaseTable') ? $storage->getDataTable() : NULL;
    if (!$base_table) {
      $this->logger()->error('Entity type {type} does not have a base table and cannot be purged.', ['type' => $entity_type]);
      return;
    }

    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');
    $changed_key = $definition->getKey('changed');

    if (!$id_key || !$rev_key) {
      $this->logger()->error('Entity type {type} must be revisionable to use trashbin purge (missing id/revision keys).', ['type' => $entity_type]);
      return;
    }

    // Build a DB query that joins to content_moderation_state_field_data on the
    // current (latest) revision id and filters to moderation_state = trash.
    $connection = \Drupal::database();
    $query = $connection->select($base_table, 'b')
      ->fields('b', [$id_key])
      ->range(0, (int) $options['max']);
    $query->condition('changed', $maximum, '<');

    // Note: innerJoin() returns the alias string, not the Select object, so it
    // must not be chained.
    $query->innerJoin(
      'content_moderation_state_field_data',
      'md',
      'md.content_entity_type_id = :etype AND md.content_entity_id = b.' . $id_key . ' AND md.content_entity_revision_id = b.' . $rev_key,
      [':etype' => $entity_type]
    );

    $query->condition('md.moderation_state', 'trash', '=');

    if ($changed_key) {
      $query->condition('b.' . $changed_key, $maximum, '<');
    }

    $ids = $query->execute()->fetchCol();

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

<?php

namespace Drupal\trashbin\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;

class TrashbinCommands extends DrushCommands {

  const TRASHBIN_ONLY_TRASH = 'trashbin_only_trash';

  private EntityTypeManagerInterface $etm;
  private TimeInterface $time;

  public function __construct(EntityTypeManagerInterface $etm, TimeInterface $time) {
    $this->etm = $etm;
    $this->time = $time;
  }

  /**
   * Delete content entities that have been in the bin for more than n days.
   *
   * @param string $entity_type
   *   Entity type to purge.
   *
   * @option $max
   *   Maximum number of entities to delete.
   * @option $days-ago
   *   Number of days that the item must be unchanged in the trashbin.
   * @usage drush --simulate trashbin:purge node
   *   Get a report of what would be purged.
   *
   * @command trashbin:purge
   */
  public function purge($entity_type, $options = ['max' => 1000, 'days-ago' => 180]) {
    $maximum = strtotime($options['days-ago'] . ' days ago', $this->time->getCurrentTime());
    $storage = $this->etm->getStorage($entity_type);
    $query = $storage->getQuery();
    $query->addTag(self::TRASHBIN_ONLY_TRASH);
    $query->condition('changed', $maximum, '<');
    $query->range(0, $options['max']);
    $ids = $query->accessCheck(FALSE)->execute();
    $this->logger()->notice('Found {count} entities to delete.', ['count' => count($ids)]);
    $entities = $storage->loadMultiple($ids);
    foreach ($entities as $entity) {
      if (Drush::simulate()) {
        $this->logger()->notice('Simulated delete of "{title}". ID={id}, {url}', ['title' => $entity->label(), 'id' => $entity->id(), 'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString()]);
      }
      else {
        $this->logger()->notice('Start delete of "{title}". ID={id}', ['title' => $entity->label(), 'id' => $entity->id()]);
        $entity->delete();
      }
    }
  }

}

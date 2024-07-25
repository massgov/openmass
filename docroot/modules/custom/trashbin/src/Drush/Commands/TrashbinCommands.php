<?php

namespace Drupal\trashbin\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TrashbinCommands extends DrushCommands {

  use AutowireTrait;

  const TRASHBIN_ONLY_TRASH = 'trashbin_only_trash';
  const TRASHBIN_PURGE = 'trashbin:purge';

  protected function __construct(
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time) {
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

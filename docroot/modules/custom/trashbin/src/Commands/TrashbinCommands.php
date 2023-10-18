<?php

namespace Drupal\trashbin\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;

class TrashbinCommands extends DrushCommands {

  const TRASHBIN_ONLY_TRASH = 'trashbin_only_trash';

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $etm;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  public function __construct(EntityTypeManagerInterface $etm, TimeInterface $time) {

    $this->etm = $etm;
    $this->time = $time;
  }

  /**
   * Delete up to 1000 entities that have been in the bin for more than 180 days.
   *
   * Use --simulate option to geta report of what would be deleted.
   *
   * @param $entity_type
   *  Entity type to purge.
   * @option $max
   *   Maximum number of entities to delete.
   * @option $days-ago
   *    Number of days that the item must be unchanged in the trashbin.
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
    $ids = $query->execute();
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

  /**
   * An example of the table output format.
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @command trashbin:token
   * @aliases token
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function token($options = ['format' => 'table']) {
    $all = \Drupal::token()->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }
}

<?php

namespace Drupal\mass_moderation_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Set a NULL value for the destination property.
 *
 * @MigrateProcessPlugin(
 *   id = "unset"
 * )
 */
class SetNull extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $row->setDestinationProperty($destination_property, NULL);
  }

}

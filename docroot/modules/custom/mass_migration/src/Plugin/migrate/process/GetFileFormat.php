<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Given the access level value returns the corresponding key.
 *
 * @MigrateProcessPlugin(
 *   id = "mass_migration_get_file_format",
 *   handle_multiples = TRUE
 * )
 */
class GetFileFormat extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($item_values, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $item_format = $item_values[0];
    $item_url = $item_values[1];

    // If item_format is Null derive from File Extension.
    if (empty($item_format) && !empty($item_url)) {
      $item_format = end(explode('.', $item_url));
    }

    return $item_format;
  }

}

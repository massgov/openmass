<?php

namespace Drupal\mass_content\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Deefine the process plugin.
 *
 * Generally we would use the 'explode' and then 'callback' functions to split
 * and trim items, but for some reason explode was getting a second value (NULL)
 * for some fields, I have no idea why. Maybe an issue with migrate_source_csv?
 *
 * @MigrateProcessPlugin(
 *   id = "mass_multiples",
 *   handle_multiples = false
 * )
 */
class MassMultiples extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_string($value)) {
      $result = explode(';', $value);
      $result = array_map('trim', $result);
    }
    else {
      $result = NULL;
    }

    return $result;
  }

}

<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips processing the current row when the input value is FALSE.
 *
 * The mass_migration_skip_on_false process plugin checks to see if the current
 * input value is FALSE (and only FALSE ... NOT empty string, Not NULL, not 0,
 * not '0', and not an empty array). If so, the further processing of the
 * property or the entire row (depending on the chosen method) is skipped and
 * will not be migrated.
 *
 * Available configuration keys:
 * - method: (optional) What to do if the input value is empty. Possible values:
 *   - row: Skips the entire row when an empty value is encountered.
 *   - process: Prevents further processing of the input property when the value
 *     is empty.
 * - message: (optional) A message to be logged in the {migrate_message_*} table
 *   for this row. Messages are only logged for the 'row' skip level. If not
 *   set, nothing is logged in the message table.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "mass_migration_skip_on_false"
 * )
 */
class SkipOnFalse extends ProcessPluginBase {

  /**
   * Skips the current row when value is FALSE.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if it is not empty.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Thrown if the source property is not set and the row should be skipped,
   *   records with STATUS_IGNORED status in the map.
   */
  public function row($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === FALSE) {
      $message = !empty($this->configuration['message']) ? $this->configuration['message'] : '';
      throw new MigrateSkipRowException($message);
    }
    return $value;
  }

  /**
   * Stops processing the current property when value is FALSE.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if it is not empty.
   *
   * @throws \Drupal\migrate\MigrateSkipProcessException
   *   Thrown if the source property is not set and rest of the process should
   *   be skipped.
   */
  public function process($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === FALSE) {
      throw new MigrateSkipProcessException();
    }
    return $value;
  }

}

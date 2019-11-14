<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Generates a path for files so we keep directory listings to a certain size.
 *
 * Copied from Concat in the core migrate module.
 *
 * Available configuration keys:
 * - delimiter: (default: '/') A delimiter, or glue string, to insert between the
 *   strings.
 * - count: (default: 2500) Limit of files in one directory. This method garuantees
 *   no more than $count files can be in one directory during migration, by counting
 *   fids in the {file_managed} table.
 *
 * Examples:
 *
 * @code
 * process:
 *   destination_path:
 *     plugin: concat
 *     source:
 *       - foo
 *       - bar
 *     delimiter: /
 *     count: 2500
 * @endcode
 *
 * This will return a path like foo/bar/aa, where aa is equivalent to the Drupal site
 * having fewer than 2500 files total.
 *
 * You can also specify a delimiter and a count other than 2500.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "mass_migration_file_path",
 *   handle_multiples = TRUE
 * )
 */
class FilePath extends ProcessPluginBase {
  /**
   * Character set to use for directory name.
   *
   * @var array
   */
  private $alphabet = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k',
    'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value)) {
      $delimiter = isset($this->configuration['delimiter']) ? $this->configuration['delimiter'] : '/';
      $count = isset($this->configuration['count']) ? $this->configuration['count'] : 2500;
      $filename = array_pop($value);
      $value[] = $this->getAlpha($count);
      $value[] = $filename;
      return implode($delimiter, $value);
    }
    else {
      throw new MigrateException(sprintf('%s is not an array', var_export($value, TRUE)));
    }
  }

  /**
   * Provide an alphabetic directory name to ensure fewer than $count files in one directory.
   *
   * @param int $count
   *   Max number of files in the directory.
   *
   * @return string
   *   Title of directory.
   */
  private function getAlpha($count) {
    $number = 0;

    $result = db_select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->orderBy('f.fid', 'DESC')
      ->range(0, 1)
      ->execute();

    $fid = $result->fetchField();

    if ($fid) {
      $number = intval($fid / $count);
    }

    return $this->toAlpha($number);
  }

  /**
   * Return the letter corresponding to the current fid divided by $count.
   *
   * @param int $number
   *   Number to divide by.
   *
   * @return string
   *   Title of directory.
   */
  private function toAlpha($number) {
    $alphaOut = '';

    $alphaCount = count($this->alphabet);

    if (!is_int($number) || $number <= 0) {
      return $this->alphabet[0];
    }

    if ($number <= $alphaCount) {
      return $this->alphabet[0] . $this->alphabet[$number - 1];
    }

    $number--;

    while ($number > 0) {
      $modulo = ($number) % $alphaCount;
      $alphaOut = $this->alphabet[$modulo] . $alphaOut;
      $number = floor((($number - $modulo) / $alphaCount));
    }

    return $alphaOut;
  }

}

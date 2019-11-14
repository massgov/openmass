<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Sanitizes the given string.
 *
 * @MigrateProcessPlugin(
 *   id = "mass_sanitize",
 *   handle_multiples = TRUE
 * )
 */
class Sanitize extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($string, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Check for empty value.
    if (empty($string)) {
      return '';
    }

    $string_sanitized = trim($this->sanitizeValue($string));
    $string_sanitized = Unicode::truncate($string_sanitized, 255);

    // Log a message if string was changed.
    if ($string != $string_sanitized) {
      $migrate_executable->saveMessage(t("The '@field' in record '@rec_id' was changed from '@original' to '@replacement'.", [
        '@field' => $destination_property,
        '@rec_id' => $row->getSource()['rec_uid'],
        '@original' => $string,
        '@replacement' => $string_sanitized,
      ]), MigrationInterface::MESSAGE_INFORMATIONAL);
    }

    return $string_sanitized;
  }

  /**
   * Sanitize a string.
   *
   * @param string $string
   *   The string to sanitize.
   *
   * @return string
   *   Sanitized file name.
   */
  private function sanitizeValue($string) {
    // Remove unsafe characters.
    $string = preg_replace('![^/\s),(:0-9A-Za-z_.-]!', '', $string);
    return Html::escape($string);
  }

}

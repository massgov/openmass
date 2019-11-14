<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Given the publishing frequency value returns the corresponding key.
 *
 * @MigrateProcessPlugin(
 *   id = "mass_migration_get_frequency_key",
 *   handle_multiples = TRUE
 * )
 */
class GetFrequencyKey extends ProcessPluginBase {
  /**
   * Allowed values for frequency field.
   *
   * @var array
   */
  private $frequencyValues;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->frequencyValues = FieldConfig::loadByName('media', 'document', 'field_publishing_frequency')->getSettings()['allowed_values'];
  }

  /**
   * {@inheritdoc}
   */
  public function transform($frequency, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Check for empty value.
    if (empty($frequency)) {
      return FALSE;
    }

    // If the value is in the database return the corresponding key.
    $key = array_search($frequency, $this->frequencyValues);
    if ($key !== FALSE) {
      return $key;
    }

    // If a frequency finished with 'ly' was not found in the previous sentence
    // then return FALSE.
    if (Unicode::substr($frequency, -2) == 'ly') {
      return FALSE;
    }

    // The problem observed in rows is we receive Annual in place of Annually
    // for example. So we log a message about the change and try with 'ly'
    // string added and test again recursively.
    $migrate_executable->saveMessage(t("The frequency in record '@rec_id' was changed from '@original' to '@replacement'.", [
      '@rec_id' => $row->getSource()['rec_uid'],
      '@original' => $frequency,
      '@replacement' => $frequency . 'ly',
    ]), MigrationInterface::MESSAGE_INFORMATIONAL);

    return $this->transform($frequency . 'ly', $migrate_executable, $row, $destination_property);
  }

}

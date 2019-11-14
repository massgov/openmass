<?php

namespace Drupal\mass_moderation_migration\Plugin\migrate\destination;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\destination\NullDestination;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Map keys as destinations for a given migration.
 *
 * @MigrateDestination(
 *   id = "id_map"
 * )
 */
class IdMap extends NullDestination {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    if (empty($configuration['keys'])) {
      throw new MigrateException('The id_map destination cannot be used without key definitions.');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->configuration['keys'];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    return array_map(
      [$row, 'getDestinationProperty'],
      array_keys($this->getIds())
    );
  }

}

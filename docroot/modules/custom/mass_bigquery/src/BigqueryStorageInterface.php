<?php

namespace Drupal\mass_bigquery;

/**
 * Defines the interface for bigquery data storage.
 */
interface BigqueryStorageInterface {

  /**
   * Queue all nodes of configured types to update.
   */
  public function queueAll(): void;

  /**
   * Update the bigquery data for specific records.
   *
   * @param array $ids
   *   The ids to update.
   *
   * @return bool
   *   True if the records updated; otherwise, False.
   */
  public function updateRecords(array $ids): bool;

}

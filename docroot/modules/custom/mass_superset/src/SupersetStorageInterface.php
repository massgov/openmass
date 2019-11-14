<?php

namespace Drupal\mass_superset;

/**
 * Defines the interface for superset data storage.
 */
interface SupersetStorageInterface {

  /**
   * Queue all nodes of configured types to update.
   */
  public function queueAll(): void;

  /**
   * Update the superset data for specific records.
   *
   * @param array $ids
   *   The ids to update.
   *
   * @return bool
   *   True if the records updated; otherwise, False.
   */
  public function updateRecords(array $ids): bool;

}

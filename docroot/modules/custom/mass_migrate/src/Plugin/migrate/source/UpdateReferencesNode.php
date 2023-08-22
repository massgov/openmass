<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references_node"
 * )
 */
class UpdateReferencesNode extends UpdateReferences {
  const SOURCE_TYPE = 'node';

}

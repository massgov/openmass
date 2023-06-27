<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references_service_details"
 * )
 */
class UpdateReferencesServiceDetails extends UpdateReferences {
  const SOURCE_TYPE = 'node';
  const SOURCE_BUNDLE = 'service_details';
}

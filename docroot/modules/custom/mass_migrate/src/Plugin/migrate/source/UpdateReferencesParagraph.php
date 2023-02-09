<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references_paragraph"
 * )
 */
class UpdateReferencesParagraph extends UpdateReferences {
  const SOURCE_TYPE = 'paragraph';

}

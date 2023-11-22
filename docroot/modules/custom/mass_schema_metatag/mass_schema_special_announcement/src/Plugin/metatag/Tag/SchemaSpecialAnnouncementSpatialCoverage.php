<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'SpatialCoverage' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_spatial_coverage",
 *   label = @Translation("spatialCoverage"),
 *   description = @Translation("What geographical area does the announcement cover?"),
 *   name = "spatialCoverage",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "place",
 *   tree_parent = {
 *     "AdministrativeArea",
 *   },
 *   tree_depth = -1,
 * )
 */
class SchemaSpecialAnnouncementSpatialCoverage extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);

    $form['@type']['#options']['State'] = $this->t('State');

    return $form;
  }

}

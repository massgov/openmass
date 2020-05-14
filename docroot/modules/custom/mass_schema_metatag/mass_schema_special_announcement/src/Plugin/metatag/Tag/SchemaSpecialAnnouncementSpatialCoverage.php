<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPlaceBase;

/**
 * Provides a plugin for the 'SpatialCoverage' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_spatial_coverage",
 *   label = @Translation("Spatial Coverage"),
 *   description = @Translation("What geographical area does the announcement cover?"),
 *   name = "spatialCoverage",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE,
 * )
 */
class SchemaSpecialAnnouncementSpatialCoverage extends SchemaPlaceBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);

    $form['@type']['#options']['State'] = $this->t('State');

    return $form;
  }

}

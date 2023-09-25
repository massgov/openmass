<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'AnnouncementLocation' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_location",
 *   label = @Translation("announcementLocation"),
 *   description = @Translation("Is there a specific Location that this involves?"),
 *   name = "announcementLocation",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "place",
 *   tree_parent = {
 *     "Place",
 *   },
 *   tree_depth = 2,
 * )
 */
class SchemaSpecialAnnouncementAnnouncementLocation extends SchemaNameBase {

  /**
   * Add AnnouncementLocation property options.
   */
  public function form($element = []) {
    $form = parent::form($element);
    $form['@type']['#options'] = [
      'CivicStructure' => $this->t('CivicStructure'),
      'LocalBusiness' => $this->t('LocalBusiness'),
    ];

    return $form;
  }

}

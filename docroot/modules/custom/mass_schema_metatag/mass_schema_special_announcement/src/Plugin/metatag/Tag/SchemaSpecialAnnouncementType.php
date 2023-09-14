<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'Type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type of page."),
 *   name = "@type",
 *   group = "schema_special_announcement",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "type",
 *   tree_parent = {
 *     "Organization",
 *   },
 *   tree_depth = -1
 * )
 */
class SchemaSpecialAnnouncementType extends SchemaNameBase {

  /**
   * Add SpecialAnnouncement property option.
   */
  public function form($element = []) {
    $form = parent::form($element);
    $form['#options'] = [
      'SpecialAnnouncement' => $this->t('SpecialAnnouncement'),
    ];
    return $form;
  }

}

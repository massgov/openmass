<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'DiseasePreventionInfo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_disease_prevention_info",
 *   label = @Translation("diseasePreventionInfo"),
 *   description = @Translation("Url or webcontent for diseasePreventionInfo."),
 *   name = "diseasePreventionInfo",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "creative_work",
 *   tree_parent = {
 *     "CreativeWork",
 *   },
 *   tree_depth = -1,
 * )
 */
class SchemaSpecialAnnouncementDiseasePreventionInfo extends SchemaNameBase {

}

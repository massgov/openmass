<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\mass_schema_metatag\Plugin\metatag\Tag\SchemaWebContentBase;

/**
 * Provides a plugin for the 'DiseaseSpreadStatistics' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_disease_spread_statistics",
 *   label = @Translation("diseaseSpreadStatistics"),
 *   description = @Translation("Url or webcontent for diseaseSpreadStatistics."),
 *   name = "diseaseSpreadStatistics",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementDiseaseSpreadStatistics extends SchemaWebContentBase {

}

<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'category' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_category",
 *   label = @Translation("Category"),
 *   description = @Translation("The category of the announcement. For COVID-19, suggested value: https://en.wikipedia.org/w/index.php?title=2019-20_coronavirus_pandemic"),
 *   name = "category",
 *   group = "schema_special_announcement",
 *   weight = 2,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementCategory extends SchemaNameBase {

}

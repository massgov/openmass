<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\mass_schema_metatag\Plugin\metatag\Tag\SchemaUrlWebContentBase;

/**
 * Provides a plugin for the 'GovernmentBenefitsInfo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_government_benefits_info",
 *   label = @Translation("governmentBenefitsInfo"),
 *   description = @Translation("Url or webcontent for governmentBenefitsInfo."),
 *   name = "governmentBenefitsInfo",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementGovernmentBenefitsInfo extends SchemaUrlWebContentBase {

}

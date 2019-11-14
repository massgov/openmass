<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_parent_location' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_parent_location",
 *   label = @Translation("mg_parent_location"),
 *   description = @Translation("The parent location for this page."),
 *   name = "mg_parent_location",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagParentLocation extends MetaNameBase {

}

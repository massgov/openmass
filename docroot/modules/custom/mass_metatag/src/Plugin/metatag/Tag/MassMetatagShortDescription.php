<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_short_description' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_short_description",
 *   label = @Translation("mg_short_description"),
 *   description = @Translation("A short description of the page."),
 *   name = "mg_short_description",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagShortDescription extends MetaNameBase {

}

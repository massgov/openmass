<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_logo_url' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_logo_url",
 *   label = @Translation("mg_logo_url"),
 *   description = @Translation("The URL of the logo image."),
 *   name = "mg_logo_url",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagLogoUrl extends MetaNameBase {

}

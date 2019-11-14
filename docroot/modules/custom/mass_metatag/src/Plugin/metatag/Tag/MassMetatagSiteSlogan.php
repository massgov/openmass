<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_site_slogan' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_site_slogan",
 *   label = @Translation("mg_site_slogan"),
 *   description = @Translation("The slogan of the site."),
 *   name = "mg_site_slogan",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagSiteSlogan extends MetaNameBase {

}

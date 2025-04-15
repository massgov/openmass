<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_microsite' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_microsite",
 *   label = @Translation("mg_microsite"),
 *   description = @Translation("The microsites of this page."),
 *   name = "mg_microsite",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagMicrosite extends MetaNameBase {

}

<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_url' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_url",
 *   label = @Translation("mg_url"),
 *   description = @Translation("The URL of page."),
 *   name = "mg_url",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagURL extends MetaNameBase {

}

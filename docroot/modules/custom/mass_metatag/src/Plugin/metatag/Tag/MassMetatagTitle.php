<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_title' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_title",
 *   label = @Translation("mg_title"),
 *   description = @Translation("The title of page."),
 *   name = "mg_title",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagTitle extends MetaNameBase {

}

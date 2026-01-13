<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_collections' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_collections",
 *   label = @Translation("mg_collections"),
 *   description = @Translation("The Collections of this page."),
 *   name = "mg_collections",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagCollection extends MetaNameBase {

}

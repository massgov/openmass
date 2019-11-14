<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_key_actions' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_key_actions",
 *   label = @Translation("mg_key_actions"),
 *   description = @Translation("Key actions related to the page."),
 *   name = "mg_key_actions",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagKeyActions extends MetaNameBase {

}

<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_overview' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_overview",
 *   label = @Translation("mg_overview"),
 *   description = @Translation("An overview of the page."),
 *   name = "mg_overview",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagOverview extends MetaNameBase {

}

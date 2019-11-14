<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mass_metatag_start_date' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_start_date",
 *   label = @Translation("mg_start_date"),
 *   description = @Translation("The start date for this page."),
 *   name = "mg_start_date",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagStartDate extends MetaNameBase {

}

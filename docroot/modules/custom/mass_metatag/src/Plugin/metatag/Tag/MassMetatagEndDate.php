<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mass_metatag_end_date' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_end_date",
 *   label = @Translation("mg_end_date"),
 *   description = @Translation("The end date for this page."),
 *   name = "mg_end_date",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagEndDate extends MetaNameBase {

}

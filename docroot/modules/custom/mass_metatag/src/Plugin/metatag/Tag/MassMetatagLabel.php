<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_labels' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_label",
 *   label = @Translation("mg_labels"),
 *   description = @Translation("The Labels of this page."),
 *   name = "mg_labels",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagLabel extends MetaNameBase {

}

<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_parent_service' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_parent_service",
 *   label = @Translation("mg_parent_service"),
 *   description = @Translation("The parent service for this page."),
 *   name = "mg_parent_service",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagParentService extends MetaNameBase {

}

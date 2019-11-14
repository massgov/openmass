<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_parent_topic' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_parent_topic",
 *   label = @Translation("mg_parent_topic"),
 *   description = @Translation("The ID used to find the parent topic for this page."),
 *   name = "mg_parent_topic",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagParentTopic extends MetaNameBase {

}

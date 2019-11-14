<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_online_contact_url' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_online_contact_url",
 *   label = @Translation("mg_online_contact_url"),
 *   description = @Translation("The URL of the Contact Page."),
 *   name = "mg_online_contact_url",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagOnlineContactUrl extends MetaNameBase {

}

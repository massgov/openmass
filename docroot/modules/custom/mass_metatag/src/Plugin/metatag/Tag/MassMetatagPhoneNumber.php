<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_phone_number' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_phone_number",
 *   label = @Translation("mg_phone_number"),
 *   description = @Translation("The phone number of the location."),
 *   name = "mg_phone_number",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagPhoneNumber extends MetaNameBase {

}

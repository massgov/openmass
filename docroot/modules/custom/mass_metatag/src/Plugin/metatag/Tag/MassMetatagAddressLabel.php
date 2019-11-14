<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_address_label' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_address_label",
 *   label = @Translation("mg_address_label"),
 *   description = @Translation("The label of the address."),
 *   name = "mg_address_label",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagAddressLabel extends MetaNameBase {

}

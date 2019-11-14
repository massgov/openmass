<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_sign_up_link' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_sign_up_link",
 *   label = @Translation("mg_sign_up_link"),
 *   description = @Translation("The Sign-Up link on the current page."),
 *   name = "mg_sign_up_link",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagSignUpLink extends MetaNameBase {

}

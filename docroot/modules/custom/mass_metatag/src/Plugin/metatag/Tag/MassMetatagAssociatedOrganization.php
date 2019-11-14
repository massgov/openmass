<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_organization' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_associated_organization",
 *   label = @Translation("mg_associated_organization"),
 *   description = @Translation("The associated organization of this page."),
 *   name = "mg_associated_organization",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagAssociatedOrganization extends MetaNameBase {

}

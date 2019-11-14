<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_external_organization' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_external_organization",
 *   label = @Translation("mg_external_organization"),
 *   description = @Translation("The external organization of this page."),
 *   name = "mg_external_organization",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagExternalOrganization extends MetaNameBase {

}

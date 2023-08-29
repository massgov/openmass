<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_parent_org' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_parent_organization",
 *   label = @Translation("mg_parent_org"),
 *   description = @Translation("The parent organizations of this page."),
 *   name = "mg_parent_org",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagParentOrganization extends MetaNameBase {

}

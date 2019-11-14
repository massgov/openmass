<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_stakeholder_org' meta tag.
 *
 * @MetatagTag(
 *   id = "mg_stakeholder_org",
 *   label = @Translation("mg_stakeholder_org"),
 *   description = @Translation("Stakeholder organization for the page."),
 *   name = "mg_stakeholder_org",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagStakeholderOrg extends MetaNameBase {

}

<?php

namespace Drupal\mass_metatag\Plugin\metatag\Group;

use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'WebPage' meta tag group.
 *
 * @MetatagGroup(
 *   id = "mass_metatag",
 *   label = @Translation("Mass Metatag"),
 *   description = @Translation("Custom metatag data for Mass.gov"),
 *   weight = 20,
 * )
 */
class MassMetatag extends GroupBase {

}

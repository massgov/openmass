<?php

/**
 * @file
 * Deploy.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\workflows\Entity\Workflow;

/**
 * Initial migration to from mass_admin_theme to Gin.
 */
function mass_gin_deploy_initial(&$sandbox) {
  // 'Taxonomy' and 'Menu' links are not needed at top level of toolbar.
  foreach ([416, 426] as $id) {
    MenuLinkContent::load($id)->delete();
  }
  // D2d redirects link - move under Mass.
  $link =  MenuLinkContent::load(411);
  $link->set('parent', 'mass_admin_pages.mass')->save();

  // Move links under Help.
  foreach ([481, 471, 466, 476] as $id) {
    MenuLinkContent::load($id)->set('parent', 'help.main')->save();
  }
}

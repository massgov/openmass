<?php

/**
 * @file
 * Deploy.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\menu_link_content\Entity\MenuLinkContent;
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
  $link = MenuLinkContent::load(411);
  $link->set('parent', 'mass_admin_pages.mass')->save();

  // Move links under Help.
  foreach ([471, 466, 476, 461] as $id) {
    MenuLinkContent::load($id)->set('parent', 'mass_admin_pages.help')->save();
  }
  // Delete old 'Need Help' menu item.
  MenuLinkContent::load(481)->delete();
}

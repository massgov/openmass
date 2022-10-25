<?php

/**
 * @file
 * Deploy.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\workflows\Entity\Workflow;

/**
 * Initial migration to from mass_admin_theme to Gin.
 */
function mass_gin_deploy_initial(&$sandbox) {
  foreach ([416, 426] as $id) {
    \Drupal\menu_link_content\Entity\MenuLinkContent::load($id)->delete();
  }
}

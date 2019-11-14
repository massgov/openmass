<?php

/**
 * @file
 * Run updates after updatedb has completed.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Implements hook_post_update_NAME().
 *
 * Hides the node `status` widget from the node form, which is confusing in
 * addition to the "Save and Publish" button set.
 *
 * Why this is here?
 * During the 8.4 core update, we needed a post update hook that would run after
 * node_post_update_configure_status_field_widget().  Since update hooks are
 * sorted alphabetically, we needed it to live in a module that would come after
 * `node` alphabetically.  This really belongs to `mass_utility`, but it had to
 * live here for technical reasons.
 */
function tfa_unblock_post_update_node_status_field_weight() {
  $query = \Drupal::entityQuery('entity_form_display')->condition('targetEntityType', 'node');
  $ids = $query->execute();
  $form_displays = EntityFormDisplay::loadMultiple($ids);
  /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay\EntityFormDisplay $display */
  foreach ($form_displays as $display) {
    $display->removeComponent('status');
    $display->save();
  }
}

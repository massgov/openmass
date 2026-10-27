<?php

/**
 * @file
 * Post-update functions for Mass.gov Caching Enhancements.
 */

/**
 * Removes the obsolete Akamai purger feature flag.
 */
function mass_caching_post_update_remove_purger_state(): void {
  \Drupal::state()->delete('mass_caching.purger');
}

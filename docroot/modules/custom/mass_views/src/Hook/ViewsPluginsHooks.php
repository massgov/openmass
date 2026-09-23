<?php

namespace Drupal\mass_views\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mass_views\Plugin\views\exposed_form\MassBetterExposedFilters;
use Drupal\mass_views\Plugin\views\exposed_form\MassInputRequired;

/**
 * Alters the Views plugin definitions used across the site.
 */
class ViewsPluginsHooks {

  /**
   * Implements hook_views_plugins_exposed_form_alter().
   */
  #[Hook('views_plugins_exposed_form_alter')]
  public function viewsPluginsExposedFormAlter(array &$definitions): void {
    // Views that ask for input before showing results are also the views
    // editors run bulk operations from, and bulk operations run the view query
    // themselves.
    if (isset($definitions['input_required'])) {
      $definitions['input_required']['class'] = MassInputRequired::class;
    }
    if (isset($definitions['bef'])) {
      $definitions['bef']['class'] = MassBetterExposedFilters::class;
    }
  }

}

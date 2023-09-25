<?php

namespace Drupal\mass_dashboard;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Class to for our pre_render callbacks Because Drupal 9 wants us to do better.
 */
class MassDashboardRender implements TrustedCallbackInterface {

  /**
   * Trusted Callback function.
   *
   * @return string[]
   *   Array of trusted callbacks.
   */
  public static function trustedCallbacks() {
    return [
      'massDashboardToolbarPrerenderTray',
    ];
  }

  /**
   * Render the MA Dashboard toolbar tray.
   *
   * Add the items in the mass-dashboard menu to the Dashboard tray.
   * Copied shamelessly from the Workbench module.
   *
   * @param array $element
   *   The tray render array.
   *
   * @return array
   *   The tray render array with the Mass Dashboard items added.
   *
   * @see toolbar_prerender_toolbar_administration_tray()
   * @see drupal_render()
   */
  public static function massDashboardToolbarPrerenderTray(array $element) {
    $menu_tree = \Drupal::service('toolbar.menu_tree');

    $parameters = new MenuTreeParameters();
    $parameters->setMinDepth(1)->setMaxDepth(1);

    $tree = $menu_tree->load('mass-dashboard', $parameters);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'toolbar_menu_navigation_links'],
    ];

    $tree = $menu_tree->transform($tree, $manipulators);

    $element['administration_menu'] = $menu_tree->build($tree);
    return $element;
  }

}

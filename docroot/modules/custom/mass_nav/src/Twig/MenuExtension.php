<?php

namespace Drupal\mass_nav\Twig;

/**
 * My menu extension.
 */
class MenuExtension extends \Twig_Extension {

  /**
   * In this function we can declare the extension function.
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('render_menu', [$this, 'renderMenu'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderMenu($menu_name) {
    $menu_tree = \Drupal::menuTree();

    // Build the typical default set of menu tree parameters.
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);

    // Load the tree based on this set of parameters.
    $tree = $menu_tree->load($menu_name, $parameters);

    // Transform the tree using the manipulators you want.
    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    // Finally, build a renderable array from the transformed tree.
    $menu = $menu_tree->build($tree);

    return ['#markup' => drupal_render($menu)];
  }

}

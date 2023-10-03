<?php

namespace Drupal\mass_admin_pages\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\Routing\Route;

/**
 * A basic Theme Negotiator.
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if (!$route instanceof Route) {
      return FALSE;
    }

    $routes_to_show_admin_theme = [
      '/node/{node}/move-children',
    ];

    return in_array($route->getPath(), $routes_to_show_admin_theme);
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return 'mass_admin_theme';
  }

}

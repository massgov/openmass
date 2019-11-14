<?php

namespace Drupal\mass_admin_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class AuthorHomePageController extends ControllerBase {

  /**
   * Returns a home page to welcome authors.
   *
   * @return array
   *   A simple renderable array.
   */
  public function authorHome() {
    return [];
  }

}

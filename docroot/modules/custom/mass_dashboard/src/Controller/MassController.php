<?php

namespace Drupal\mass_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for book routes.
 */
class MassController extends ControllerBase {

  /**
   * Returns an administrative overview of all books.
   *
   * @return array
   *   A render array representing the administrative page content.
   */
  public function redirectRedirect() {

    return $this->redirect('redirect.list');

  }

}

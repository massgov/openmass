<?php

namespace Drupal\mass_gin\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Mass Gin routes.
 */
class MassGinController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}

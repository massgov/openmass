<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ImpactController.
 *
 * @package Drupal\mass_translations\Controller
 */

class TranslationsController extends ControllerBase {

  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => $this->t('Hello world'),
    );
  }

}

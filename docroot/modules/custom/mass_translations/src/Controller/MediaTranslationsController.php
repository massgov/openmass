<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class MediaTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class MediaTranslationsController extends ControllerBase {

  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => $this->t('Hello world'),
    );
  }

}

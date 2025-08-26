<?php

namespace Drupal\ai_content_advisor\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class.
 */
class AnalyzeContentController extends ControllerBase {

  /**
   * Build the page.
   */
  public function printReport($node) {
    $form = \Drupal::formBuilder()->getForm('\Drupal\ai_content_advisor\Form\AnalyzeNodeForm');
    return $form;
  }

  /**
   * Page title.
   */
  public function getTitle() {
    return $this->t('Analyze Content');
  }

}

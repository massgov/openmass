<?php

namespace Drupal\ai_seo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class.
 */
class AnalyzeContentController extends ControllerBase {

  /**
   * Build the page.
   */
  public function printReport($node) {
    $form = \Drupal::formBuilder()->getForm('\Drupal\ai_seo\Form\AnalyzeNodeForm');
    return $form;
  }

  /**
   * Page title.
   */
  public function getTitle() {
    return $this->t('Analyze SEO');
  }

}

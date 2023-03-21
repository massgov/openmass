<?php

namespace Drupal\mass_flagging\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * HelpPageController class.
 */
class HelpPageController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   *   Array to get rendered.
   */
  public function content() {
    \Drupal::moduleHandler()->loadInclude('mass_flagging', 'inc');
    return [
      '#type' => 'markup',
      '#markup' => get_mass_flagging_help_text(),
    ];
  }

}

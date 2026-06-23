<?php

namespace Drupal\mass_translations;

use Drupal\mass_content\Entity\Bundle\node\FormPageBundle;

/**
 * Provides functionality for attaching specific libraries to renderable variables.
 */
class ClassResolverAttachIframeTranslationLibrary {

  /**
   * Attaches a specific library to variables based on conditions.
   *
   * @param array &$variables
   *   An associative array containing renderable variables for a Drupal node.
   *
   * @return void
   *   This method does not return any value.
   */
  public function attach(array &$variables): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['node'];
    if ($variables["view_mode"] !== "full") {
      return;
    }

    if (!($node instanceof FormPageBundle)) {
      return;
    }

    if ($node->isGravityForms()) {
      // Attach the translate-communication library to all pages
      $variables['#attached']['library'][] = 'mass_translations/translate-communication';
    }
  }

}

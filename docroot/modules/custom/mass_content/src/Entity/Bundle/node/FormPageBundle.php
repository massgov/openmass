<?php

namespace Drupal\mass_content\Entity\Bundle\node;

/**
 * A bundle class for node entities.
 */
class FormPageBundle extends NodeBundle {

  /**
   * Get platform.
   */
  public function getPlatform(): string {
    return $this->get('field_form_platform')->getString();
  }
}

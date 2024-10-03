<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A bundle class for node entities.
 */
class FormPageBundle extends NodeBundle {

  /**
   * Get platform.
   */
  public function getPlatform(): FieldItemListInterface {
    return $this->get('field_form_platform');
  }

}

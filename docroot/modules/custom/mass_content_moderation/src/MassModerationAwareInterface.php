<?php

namespace Drupal\mass_content_moderation;

use Drupal\Core\Field\FieldItemListInterface;

interface MassModerationAwareInterface {

  /**
   * Get the moderation state.
   */
  public function getModerationState(): FieldItemListInterface;

}

<?php

namespace Drupal\mass_content_moderation;

use Drupal\Core\Field\FieldItemListInterface;

trait MassModerationTrait {
  public function getModerationState(): FieldItemListInterface {
    return $this->get('moderation_state');
  }
}

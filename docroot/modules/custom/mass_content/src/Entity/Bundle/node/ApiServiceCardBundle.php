<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * A bundle class for node entities.
 */
class ApiServiceCardBundle extends NodeBundle {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Older revisions can predate this field and must still expose a boolean.
    if ($this->hasField('field_api_srv_card_apc_enabled') && $this->get('field_api_srv_card_apc_enabled')->isEmpty()) {
      $this->set('field_api_srv_card_apc_enabled', 0);
    }
  }

}

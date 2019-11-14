<?php

namespace Drupal\mass_moderation_migration\Plugin\Deriver;

/**
 * Save deriver.
 */
class SaveDeriver extends ModerationDeriver {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = parent::getDerivativeDefinitions($base_plugin_definition);

    foreach ($this->derivatives as $id => &$derivative) {
      $derivative['source']['plugin'] = "content_entity_moderation:$id";
    }
    return $this->derivatives;
  }

}

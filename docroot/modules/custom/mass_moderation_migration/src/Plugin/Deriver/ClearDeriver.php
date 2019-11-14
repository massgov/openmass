<?php

namespace Drupal\mass_moderation_migration\Plugin\Deriver;

/**
 * Clear deriver.
 */
class ClearDeriver extends SaveDeriver {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = parent::getDerivativeDefinitions($base_plugin_definition);

    foreach ($this->derivatives as $id => &$derivative) {
      $entity_type = $this->entityTypeManager->getDefinition($id);

      $key = $entity_type->getKey('id');
      $derivative['process'][$key] = $key;

      if ($entity_type->isRevisionable()) {
        $key = $entity_type->getKey('revision');
        $derivative['process'][$key] = $key;
      }

      if ($entity_type->isTranslatable()) {
        $key = $entity_type->getKey('langcode');
        $derivative['process'][$key] = $key;
      }

      $derivative['destination']['plugin'] = "entity_revision:$id";
      $derivative['migration_dependencies']['required'][] = "mass_moderation_migration_save:$id";
    }
    return $this->derivatives;
  }

}

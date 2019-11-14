<?php

namespace Drupal\mass_moderation_migration\Plugin\Deriver;

/**
 * Deriver for mass_moderation_restore migration.
 */
class RestoreDeriver extends ModerationDeriver {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = parent::getDerivativeDefinitions($base_plugin_definition);

    foreach ($this->derivatives as $id => &$derivative) {
      $keys = $this->entityTypeManager->getDefinition($id)->getKeys();

      $derivative['source']['plugin'] = "content_entity_revision:$id";

      foreach (['id', 'revision'] as $key) {
        $key = $keys[$key];
        $derivative['process'][$key] = $key;
      }

      $derivative['process']['moderation_state'][0] += [
        'source' => [
          $keys['id'],
          $keys['revision'],
        ],
        'migration' => [
          "mass_moderation_migration_save:$id",
        ],
      ];
      $derivative['destination']['plugin'] = "entity_revision:$id";
      $derivative['migration_dependencies']['optional'][] = "mass_moderation_migration_save:$id";
    }
    return $this->derivatives;
  }

}

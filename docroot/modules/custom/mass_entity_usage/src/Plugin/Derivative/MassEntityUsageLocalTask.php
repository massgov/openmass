<?php

namespace Drupal\mass_entity_usage\Plugin\Derivative;

use Drupal\entity_usage\Plugin\Derivative\EntityUsageLocalTask;

/**
 * Provides local task definitions for all entity types.
 */
class MassEntityUsageLocalTask extends EntityUsageLocalTask {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    parent::getDerivativeDefinitions($base_plugin_definition);

    foreach ($this->derivatives as &$entry) {
      $entry['title'] = $this->t('Pages linking here');
      $entry['weight'] = 3;
    }

    return $this->derivatives;
  }

}

<?php

namespace Drupal\mass_content\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Implement dynamic logic to provide values for the same keys as in example.links.task.yml.
    $this->derivatives['mass_content.change_parents'] = $base_plugin_definition;
    $this->derivatives['mass_content.change_parents']['title'] = "Move Children";
    $this->derivatives['mass_content.change_parents']['route_name'] = 'view.change_parents.page_1';
    $this->derivatives['mass_content.change_parents']['base_route'] = 'entity.node.canonical';
    $this->derivatives['mass_content.change_parents']['weight'] = 50;

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}

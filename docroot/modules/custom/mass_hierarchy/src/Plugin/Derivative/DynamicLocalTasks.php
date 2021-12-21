<?php

namespace Drupal\mass_hierarchy\Plugin\Derivative;

use Drupal\entity_hierarchy\Plugin\Derivative\DynamicLocalTasks as hierarchyHierarchyDinamicLocalTasks;

/**
 * Changes title and weight for local task "Hierarchy".
 */
class DynamicLocalTasks extends hierarchyHierarchyDinamicLocalTasks {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->entityFieldManager->getFieldMapByFieldType('entity_reference_hierarchy') as $entity_type_id => $fields) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$entity_type->hasLinkTemplate('canonical') || isset($this->derivatives["$entity_type_id.entity_hierarchy_reorder"])) {
        continue;
      }
      $this->derivatives["$entity_type_id.entity_hierarchy_reorder"] = [
        'route_name' => "entity.$entity_type_id.entity_hierarchy_reorder",
        'title' => $this->t('Hierarchy'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 45,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}

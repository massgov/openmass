<?php

namespace Drupal\mass_entityreference\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "mass_select_filter",
 *   label = @Translation("Select Filter"),
 *   group = "mass_select_filter",
 *   weight = 0
 * )
 */
class MassFilterEntitiesSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $target_type = $this->configuration['target_type'];
    // $handler_settings = $this->configuration['handler_settings'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);
    $types = $this->configuration['target_bundles'];

    // Get the users selected filter.
    $cookie = \Drupal::requestStack()->getCurrentRequest()->cookies->get('Drupal_visitor_autocomplete_select_filter');
    if (!empty($cookie)) {
      $types = unserialize($cookie);
    }

    $query = $this->entityTypeManager->getStorage($target_type)->getQuery()->accessCheck();

    // If 'target_bundles' is NULL, all bundles are referenceable, no further
    // conditions are needed.
    if (isset($types) && is_array($types)) {
      // If 'target_bundles' is an empty array, no bundle is referenceable,
      // force the query to never return anything and bail out early.
      if ($types === []) {
        $query->condition($entity_type->getKey('id'), NULL, '=');
        return $query;
      }
      else {
        $query->condition($entity_type->getKey('bundle'), $types, 'IN');
      }
    }

    if (isset($match) && $label_key = $entity_type->getKey('label')) {
      $query->condition($label_key, $match, $match_operator);
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if (!empty($handler_settings['sort'])) {
      $sort_settings = $handler_settings['sort'];
      if ($sort_settings['field'] != '_none') {
        $query->sort($sort_settings['field'], $sort_settings['direction']);
      }
    }

    return $query;
  }

}

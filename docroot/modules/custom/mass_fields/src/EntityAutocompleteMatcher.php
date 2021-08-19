<?php

namespace Drupal\mass_fields;

use Drupal\Core\Entity\EntityAutocompleteMatcher as DefaultAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;

/**
 * Matcher class to get autocompletion results for entity reference.
 *
 * Outputs autcomplete results with a custom format, and shows up to 100
 * matches.
 */
class EntityAutocompleteMatcher extends DefaultAutocompleteMatcher {

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityAutocompleteMatcher constructor.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = [];

    $options = [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $options += $selection_settings;
    $handler = $this->selectionManager->getInstance($options);

    if (isset($string)) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 100);

      $entity_type_labels = [];
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $entity_type => $values) {
        foreach ($values as $entity_id => $label) {
          if (!isset($entity_type_labels[$entity_type])) {
            $node_type = $this->entityTypeManager->getStorage('node_type')
              ->load($entity_type);
            if (isset($node_type)) {
              $entity_type_label = $node_type->label();
              $entity_type_labels[$entity_type] = $entity_type_label;
            }
          }
          if (isset($entity_type_labels[$entity_type])) {
            $key = "$label ($entity_id) - {$entity_type_labels[$entity_type]}";
          }
          else {
            $key = "$label ($entity_id)";
          }
          // Strip troublesome characters like starting/trailing white spaces, line breaks and tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = [
            'value' => $key,
            'label' => $entity_type_labels[$entity_type] . ': ' . $label,
          ];
        }
      }
    }

    return $matches;
  }

}

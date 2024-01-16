<?php

namespace Drupal\mass_fields;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcher as DefaultAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
      foreach ($entity_labels as $bundle => $values) {
        foreach ($values as $entity_id => $label) {
          if (!isset($entity_type_labels[$bundle])) {
            if (isset($handler->entityTypeBundleInfo)) {
              $bundle_info = $handler->entityTypeBundleInfo->getBundleInfo($target_type)[$bundle];
              if (!empty($bundle_info)) {
                $entity_type_label = $bundle_info['label'];
                $entity_type_labels[$bundle] = $entity_type_label;
              }
            }
            else {
              $node_type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
              if (isset($node_type)) {
                $entity_type_label = $node_type->label();
                $entity_type_labels[$bundle] = $entity_type_label;
              }
            }
          }
          if (isset($entity_type_labels[$bundle])) {
            $key = "$label ($entity_id) - {$entity_type_labels[$bundle]}";
          }
          else {
            $key = "$label ($entity_id)";
          }
          // Strip troublesome characters like starting/trailing white spaces, line breaks and tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $entity = \Drupal::entityTypeManager()->getStorage($target_type)->load($entity_id);
          if ($entity->getEntityType()->id() == 'node' && !$entity->isPublished()) {
            $label .= " (unpublished)";
          }
          $matches[] = [
            'value' => $key,
            'label' => isset($entity_type_labels[$bundle]) ? $entity_type_labels[$bundle] . ': ' . $label : $label,
            'bundle' => $bundle,
          ];
        }
      }
    }

    // Custom sort function
    usort($matches, function ($a, $b) {
      // Define content type priority
      $priority = ['org_page' => 1, 'topic_page' => 2, 'service_page' => 3];

      // Get priorities based on bundle
      // Priority 4: All other content types (default for those not explicitly mentioned)
      $a_priority = $priority[$a['bundle']] ?? 4;
      $b_priority = $priority[$b['bundle']] ?? 4;

      // Sort by priority, then alphabetically if priorities are equal
      if ($a_priority === $b_priority) {
        return strcmp($a['label'], $b['label']);
      }
      return $a_priority - $b_priority;
    });

    return $matches;
  }

}

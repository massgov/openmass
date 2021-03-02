<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mass_metatag_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "mass_metatag_type",
 *   label = @Translation("mg_type"),
 *   description = @Translation("The type of page."),
 *   name = "mg_type",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagType extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!empty($element['#attributes']['content'])) {
      $element['#attributes']['content'] =
        _mass_metatag_slugify($element['#attributes']['content'], FALSE);

      // Get Binder data type, field_binder_data_type.
      if ($node instanceof NodeInterface && $node->bundle() == 'binder') {
        $term_ref_name = 'field_binder_data_type';
        $outputValue = '';
        if ($node->hasField($term_ref_name) && $data_type = $node->get($term_ref_name)) {
          $items = $data_type->referencedEntities();
          if (!empty($items)) {
            $type_name = $items[0]->get('name')->getString();
            $data_resources = $node->get('field_data_resource_type');
            $tags = [];

            // field_data_resource_type has a value + data type = Data resource.
            if (!$data_resources->isEmpty() && strpos($type_name, 'resource') !== FALSE) {
              foreach ($data_resources->referencedEntities() as $k => $resource) {
                $tags[$k] = $resource->get('field_dataresource_metatag')->getString();
              }
              $outputValue = ', ' . implode(', ', $tags);
            }

            elseif ($data_resources->isEmpty() && strpos($type_name, 'resource') !== FALSE) {
              // Type = Data resource + 'field_data_resource_type' = unchecked.
              $outputValue = ', ' . $type_name;
            }

            elseif (!$items[0]->get('field_details_datatype_metatag')->isEmpty()) {
              // Anything but 'Data resource'.
              $outputValue = ', ' . $items[0]->get('field_details_datatype_metatag')->getString();
            }
          }
        }
        // Add the type value to the initial mg_type value, field_binder_binder_type.
        $element['#attributes']['content'] = $element['#attributes']['content'] . $outputValue;
      }
    }
    return $element;
  }

}

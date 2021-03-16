<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mass_metatag_resource_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "mass_metatag_type",
 *   label = @Translation("mg_resource_type"),
 *   description = @Translation("The data resource type of page."),
 *   name = "mg_resource_type",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagResourceType extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    $node = \Drupal::routeMatch()->getParameter('node');

      if ($node instanceof NodeInterface && $node->bundle() == 'binder') {
        if ($node->get('field_binder_data_type')) {
          $items = $node->get('field_binder_data_type')->referencedEntities();
          if (!empty($items)) {
            // Check Binder data type, field_binder_data_type to determin if mg_resource_type is neccessary or not.
            if ($items[0]->get('name')->getString() === 'Data resource') {

              $data_resources = $node->get('field_data_resource_type');
              $types = [];
              $outputValue = '';

              // field_data_resource_type has a value + data type = Data resource.
              if (!$data_resources->isEmpty()) {

                // Values for mg_resource_type[NEW]
                foreach ($data_resources->referencedEntities() as $k => $resource) {
                  $types[$k] = $resource->get('field_dataresource_metatag')->getString();
                }
                $outputValue = implode(', ', $types);
              }
              else {
                $outputValue = "null";
              }

              if (empty($element)) {
                $element = [
                // $tag = [
                  '#tag' => 'meta',
                  '#attributes' => [
                    'name' => 'mg_resource_type',
                    'content' => $outputValue,
                  ]
                ];
                // return $tag;
              }
            }
          }
        }
      }
    // return $attachments;
    return $element;
  }
}

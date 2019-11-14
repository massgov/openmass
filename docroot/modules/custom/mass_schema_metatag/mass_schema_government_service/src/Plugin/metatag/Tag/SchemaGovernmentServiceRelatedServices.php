<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\node\Entity\Node;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_government_service_related_services' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_related_services",
 *   label = @Translation("isRelatedTo"),
 *   description = @Translation("The related services."),
 *   name = "isRelatedTo",
 *   group = "schema_government_service",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaGovernmentServiceRelatedServices extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value) {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    // Explode the values, which are target ids of the service page entities
    // referenced on the field.
    $content = explode(', ', $this->value());

    if (!empty($element) && is_array($content)) {
      $element['#attributes']['content'] = [];

      // Iterate through each target id and get the url of each node to
      // reference as a related service.
      foreach ($content as $target_id) {
        $node = Node::load($target_id);
        if (!$node) {
          continue;
        }
        $element['#attributes']['content'][] = [
          '@type' => 'Service',
          '@id' => $node->toUrl('canonical', ['absolute' => TRUE])->toString() . '#services',
        ];
      }
    }
    return $element;
  }

}

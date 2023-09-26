<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains PreventEditGovLinkConstraint class.
 */

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Prevent node text fields from containing edit.mass.gov.
 *
 * @Constraint(
 *   id = "PreventEditGovLink",
 *   label = @Translation("Prevent node text fields from containing edit.mass.gov.", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class PreventEditGovLinkConstraint extends CompositeConstraintBase {
  /**
   * Message shown when a node contains an edit.mass.gov link.
   *
   * @var string
   */
  public $message = 'You must link to www.mass.gov or another public-facing website. Links to edit.mass.gov are not permitted.';

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    $node_field_names = [];
    $node_types = NodeType::loadMultiple();
    // This will cover all node fields with the following field types.
    $field_types = [
      'string',
      'string_long',
      'text',
      'text_long',
      'text_with_summary',
      'link',
    ];
    foreach ($node_types as $type => $node_type_definition) {
      $node_fields = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', $type);
      foreach ($node_fields as $definition) {
        if ($definition instanceof FieldConfig) {
          /** @var \Drupal\field\Entity\FieldConfig $definition */
          $field_type = $definition->getType();
          if (in_array($field_type, $field_types)) {
            // Get name will be the field id.
            $name = $definition->getName();
            $node_field_names[$name] = $name;
          }
        }
      }
    }
    $paragraph_bundles = ParagraphsType::loadMultiple();
    foreach ($paragraph_bundles as $type => $paragraph_type_definition) {
      $paragraph_fields = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('paragraph', $type);
      foreach ($paragraph_fields as $definition) {
        if ($definition instanceof FieldConfig) {
          /** @var \Drupal\field\Entity\FieldConfig $definition */
          $field_type = $definition->getType();
          if (in_array($field_type, $field_types)) {
            // Get name will be the field id.
            $name = $definition->getName();
            $node_field_names[$name] = $name;
          }
        }
      }
    }
    return $node_field_names;
  }

}

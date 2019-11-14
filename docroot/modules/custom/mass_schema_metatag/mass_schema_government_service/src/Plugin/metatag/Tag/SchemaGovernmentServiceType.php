<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_government_service_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of government service."),
 *   name = "@type",
 *   group = "schema_government_service",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaGovernmentServiceType extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'GovernmentService' => $this->t('GovernmentService'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}

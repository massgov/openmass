<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_apply_action_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of How-To Page. i.e. The act of registering to an organization/service without the guarantee to receive it."),
 *   name = "@type",
 *   group = "schema_apply_action",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaApplyActionType extends SchemaNameBase {

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
        'ApplyAction' => $this->t('ApplyAction'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}

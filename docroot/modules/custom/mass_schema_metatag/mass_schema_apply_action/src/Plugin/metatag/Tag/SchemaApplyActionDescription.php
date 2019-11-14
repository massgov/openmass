<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_apply_action_description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_description",
 *   label = @Translation("description"),
 *   description = @Translation("A description of the item."),
 *   name = "description",
 *   group = "schema_apply_action",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaApplyActionDescription extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_how_to_lede]';
    return $form;
  }

}

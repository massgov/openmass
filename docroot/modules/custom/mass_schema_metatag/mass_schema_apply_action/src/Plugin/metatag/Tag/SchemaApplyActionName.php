<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_apply_action_name' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_name",
 *   label = @Translation("name"),
 *   description = @Translation("The name of the item."),
 *   name = "name",
 *   group = "schema_apply_action",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaApplyActionName extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

}

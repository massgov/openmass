<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_apply_action_target' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_target",
 *   label = @Translation("target"),
 *   description = @Translation("Indicates a target EntryPoint for an Action."),
 *   name = "target",
 *   group = "schema_apply_action",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaApplyActionTarget extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_how_to_link_1]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    $targets = json_decode($this->value(), TRUE);

    $element['#attributes']['content'] = [];

    // Iterate through each target and get the name and url for the objects.
    foreach ($targets as $target) {
      $name = !empty($target['name']) ? $target['name'] : '';
      $url = !empty($target['url']) ? $target['url'] : '';
      $element['#attributes']['content'][] = [
        '@type' => 'EntryPoint',
        'name' => $name,
        'url' => $url,
      ];
    }
    return $element;
  }

}

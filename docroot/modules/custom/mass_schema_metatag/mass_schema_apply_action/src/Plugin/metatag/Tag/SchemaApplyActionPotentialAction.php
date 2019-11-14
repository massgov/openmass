<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_apply_action_potential_action' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_potential_action",
 *   label = @Translation("Potential Action"),
 *   description = @Translation("Indicates a potential Action, which describes an idealized action in which this thing would play an 'object' role."),
 *   name = "potentialAction",
 *   group = "schema_apply_action",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaApplyActionPotentialAction extends SchemaNameBase {

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

    // Since there could be multiple values, explode the string value.
    $content = explode(', ', $this->value());

    $element['#attributes']['content'] = [];
    foreach ($content as $link_values) {
      // Decode the link values.
      $link_values = json_decode($link_values, TRUE);
      if (is_array($link_values)) {
        // For each link item, append the values of the 'name' and 'url' to the
        // 'content' key. This will be the value outputted on the markup.
        foreach ($link_values as $item) {
          $element['#attributes']['content'][] = [
            'name' => $item['name'],
            'url' => $item['url'],
          ];
        }
      }
      else {
        $element['#attributes']['content'][] = [
          'name' => $link_values['name'],
          'url' => $link_values['url'],
        ];
      }
    }

    return $element;
  }

}

<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_event_potential_action' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_potential_action",
 *   label = @Translation("potentialAction"),
 *   description = @Translation("Indicates a potential Action, which describes an idealized action in which this thing would play an 'object' role."),
 *   name = "potentialAction",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaEventPotentialAction extends SchemaNameBase {

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

    // There could not be multiple values.
    $content = json_decode($this->value(), TRUE);

    $element['#attributes']['content'] = [];
    foreach ($content as $link_values) {
      $element['#attributes']['content'][] = [
        'name' => $link_values['name'],
        'url' => $link_values['url'],
      ];
    }

    return $element;
  }

}

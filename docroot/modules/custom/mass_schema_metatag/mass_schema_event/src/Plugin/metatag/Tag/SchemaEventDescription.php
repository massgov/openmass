<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_description' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_description",
 *   label = @Translation("description"),
 *   description = @Translation("A description of the item."),
 *   name = "description",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventDescription extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_lede]';
    return $form;
  }

}

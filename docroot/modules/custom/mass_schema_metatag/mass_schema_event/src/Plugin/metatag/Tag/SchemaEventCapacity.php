<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_capacity' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_capacity",
 *   label = @Translation("maximumAttendeeCapacity"),
 *   description = @Translation("The total number of individuals that may attend an event or venue."),
 *   name = "maximumAttendeeCapacity",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "integer",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventCapacity extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_lede]';
    return $form;
  }

}

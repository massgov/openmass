<?php

namespace Drupal\mass_schema_place\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_place_disambiguating_description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_place_disambiguating_description",
 *   label = @Translation("disambiguatingDescription"),
 *   description = @Translation("The disambigurating description of the place."),
 *   name = "disambiguatingDescription",
 *   group = "schema_place",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaPlaceDisambiguatingDescription extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:summary]';
    return $form;
  }

}

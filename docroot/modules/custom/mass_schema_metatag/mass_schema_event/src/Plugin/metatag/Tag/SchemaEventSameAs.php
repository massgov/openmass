<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_collection_page_related_link' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_same_as",
 *   label = @Translation("sameAs"),
 *   description = @Translation("URL of a reference Web page that unambiguously indicates the item's identity. E.g. the URL of the item's Wikipedia page, Wikidata entry, or official website."),
 *   name = "sameAs",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaEventSameAs extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_related_guides]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();

    // Get the links.
    $links = json_decode($this->value(), TRUE);

    // Assign the links array to the element for output.
    if (!empty($element) && is_array($links)) {
      $element['#attributes']['content'] = array_column($links, 'url');
    }

    return $element;
  }

}

<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_collection_page_main_entity' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_main_entity",
 *   label = @Translation("mainEntity"),
 *   description = @Translation("Indicates the primary entity described in some page or other CreativeWork."),
 *   name = "mainEntity",
 *   group = "schema_collection_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaCollectionPageMainEntity extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_related_guides]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value): void {
    // Metatag can provide NULL when no defaults are set yet.
    // Normalize to a string so ::value() never returns NULL (strict typing in D11).
    if ($value === NULL) {
      $this->value = '';
      return;
    }

    // For safety, normalize arrays to a JSON string (should be rare for multiple=FALSE).
    if (is_array($value)) {
      $this->value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
      return;
    }

    $this->value = (string) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    // Get the links.
    $value = $this->value();
    if (is_array($value)) {
      // Defensive: multiple=FALSE should not yield arrays, but handle it anyway.
      $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $value = trim((string) $value);

    if ($value === '') {
      return $element;
    }

    $links = json_decode($value, TRUE);

    // Assign the links array to the element for output.
    if (!empty($element) && is_array($links)) {
      $element['#attributes']['content'] = $links;
    }

    return $element;
  }

}

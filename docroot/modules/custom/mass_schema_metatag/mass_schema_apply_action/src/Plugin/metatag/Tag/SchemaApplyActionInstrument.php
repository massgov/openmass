<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_apply_action_instrument' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_instrument",
 *   label = @Translation("instrument"),
 *   description = @Translation("A instrument page of the item."),
 *   name = "instrument",
 *   group = "schema_apply_action",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaApplyActionInstrument extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_how_to_methods_5]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value): void
  {
    // Metatag can provide NULL when no defaults are set yet.
    // Normalize to a string and collapse excess whitespace/newlines for JSON-LD.
    if ($value === NULL) {
      $this->value = '';
      return;
    }

    if (is_array($value)) {
      // Defensive: `multiple=FALSE` should not yield arrays, but handle it anyway.
      $value = implode("\n", array_map('strval', $value));
    }

    $value = (string) $value;

    // Normalize line breaks.
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    // If tokens/rendering produced markup (or theme debug comments), strip it.
    // Theme debug output appears as HTML comments and must not leak into JSON-LD.
    $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Split into lines, trim each line, and remove blank lines.
    $lines = preg_split('/\n+/', $value) ?: [];
    $lines = array_map('trim', $lines);
    $lines = array_values(array_filter($lines, static fn(string $l) => $l !== ''));

    // Join with a single newline + space to match previous output style.
    $this->value = $lines ? implode("\n ", $lines) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    // Ensure the normalized value is used.
    $element = parent::output();

    $value = $this->value();
    if (is_array($value)) {
      $value = implode("\n ", $value);
    }
    $value = trim((string) $value);

    // Extra safety: ensure no markup/comments leak into JSON-LD.
    $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = trim($value);

    if ($value === '') {
      return $element;
    }

    // As an extra safeguard, collapse any remaining runs of whitespace.
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\n{2,}/u', "\n ", $value) ?? $value;

    $element['#attributes']['content'] = $value;

    return $element;
  }

}

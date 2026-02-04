<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_start_date' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_start_date",
 *   label = @Translation("startDate"),
 *   description = @Translation("The start date and time of the item (in ISO 8601 date format)."),
 *   name = "startDate",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "date",
 *   tree_parent = {},
 *   tree_depth = 0
 * )
 */
class SchemaEventStartDate extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function setValue($value): void
  {
    // Metatag can provide NULL when no defaults are set yet.
    // Normalize to a string so ::value() never returns NULL (strict typing in D11).
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

    // Strip theme debug comments / markup if token rendering returned HTML.
    $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Split into lines, trim each line, and remove blank lines.
    $lines = preg_split('/\n+/', $value) ?: [];
    $lines = array_map('trim', $lines);
    $lines = array_values(array_filter($lines, static fn(string $l) => $l !== ''));

    // Merge a dangling dash line with the following line so we keep the original
    // " - 2013-01-01T..." formatting.
    $normalized = [];
    for ($i = 0; $i < count($lines); $i++) {
      if ($lines[$i] === '-' && isset($lines[$i + 1])) {
        $normalized[] = ' - ' . $lines[$i + 1];
        $i++;
        continue;
      }
      $normalized[] = $lines[$i];
    }

    // Join with a single newline to match previous output style.
    $this->value = $normalized ? implode("\n", $normalized) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();

    // Ensure we always have a clean string to output.
    $value = $this->value();
    if (is_array($value)) {
      $value = implode("\n", $value);
    }
    $value = trim((string) $value);

    if ($value === '') {
      return $element;
    }

    // Extra safety: remove any markup/comments that might slip through.
    $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = trim($value);

    $element['#attributes']['content'] = $value;

    return $element;
  }
}

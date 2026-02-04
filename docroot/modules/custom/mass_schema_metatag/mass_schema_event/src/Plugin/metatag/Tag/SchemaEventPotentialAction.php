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
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value): void
  {
    // Metatag can provide NULL (no defaults yet) or an array when `multiple=TRUE`.
    // Normalize to a string so ::value() never returns NULL (strict typing in D11).
    if ($value === NULL) {
      $this->value = '';
      return;
    }

    if (is_array($value)) {
      // Join multiple values into a single comma-separated string.
      $value = array_values(array_filter($value, static fn($v) => $v !== NULL && $v !== ''));
      $this->value = $value ? implode(', ', array_map('strval', $value)) : '';
      return;
    }

    $this->value = (string) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();

    // Ensure we always have a string to work with.
    $value = $this->value();
    if (is_array($value)) {
      $value = implode(', ', $value);
    }
    $value = trim((string) $value);

    // No configured values.
    if ($value === '') {
      return $element;
    }

    $element['#attributes']['content'] = [];

    // This tag is marked `multiple=TRUE`. Values may be stored as:
    // - A single JSON array: [{"name":"...","url":"..."}, ...]
    // - Multiple JSON objects separated by commas.
    $decoded = NULL;
    if (str_starts_with($value, '[')) {
      $decoded = json_decode($value, TRUE);
      if (!is_array($decoded)) {
        return $element;
      }

      foreach ($decoded as $item) {
        if (!is_array($item) || empty($item['name']) || empty($item['url'])) {
          continue;
        }
        $element['#attributes']['content'][] = [
          'name' => $item['name'],
          'url' => $item['url'],
        ];
      }

      return $element;
    }

    // Fallback: treat as comma-separated JSON objects.
    foreach (array_map('trim', explode(',', $value)) as $chunk) {
      if ($chunk === '') {
        continue;
      }
      $item = json_decode($chunk, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE || !is_array($item)) {
        continue;
      }
      if (empty($item['name']) || empty($item['url'])) {
        continue;
      }
      $element['#attributes']['content'][] = [
        'name' => $item['name'],
        'url' => $item['url'],
      ];
    }

    return $element;
  }

}

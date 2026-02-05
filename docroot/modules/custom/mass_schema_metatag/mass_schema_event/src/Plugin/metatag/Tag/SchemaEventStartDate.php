<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\Core\Datetime\DrupalDateTime;

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
  public function output(): array {
    $element = parent::output();

    $raw = $this->value();
    if ($raw === NULL || $raw === '') {
      return $element;
    }

    if (is_array($raw)) {
      $raw = implode("\n", $raw);
    }

    // Raw token input: UTC values separated by newlines.
    $parts = preg_split('/\R+/', (string) $raw) ?: [];
    $parts = array_map('trim', $parts);
    $parts = array_values(array_filter($parts, static fn(string $v) => $v !== ''));

    if (empty($parts)) {
      return $element;
    }

    // Convert UTC → site timezone.
    $site_tz = \Drupal::config('system.date')->get('timezone.default') ?: date_default_timezone_get();

    $dates = [];
    foreach ($parts as $utc) {
      try {
        $dt = new DrupalDateTime($utc, 'UTC');
        $dt->setTimezone(new \DateTimeZone($site_tz));
        $dates[] = $dt->format('Y-m-d\\TH:i:sO');
      }
      catch (\Exception $e) {
        // Ignore invalid values.
      }
    }

    if (empty($dates)) {
      return $element;
    }

    if (isset($dates[1])) {
      $element['#attributes']['content'] = $dates[0] . "\n - " . $dates[1];
    }
    else {
      $element['#attributes']['content'] = $dates[0];
    }

    return $element;
  }

}

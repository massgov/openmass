<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\Core\Url;

/**
 * Provides a plugin for the 'schema_web_page_is_based_on' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_is_based_on",
 *   label = @Translation("isBasedOn"),
 *   description = @Translation("A resource that was used in the creation of this resource. This term can be repeated for multiple sources. For example, http://example.com/great-multiplication-intro.html."),
 *   name = "isBasedOn",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageIsBasedOn extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_sources]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    if (empty($element)) {
      return $element;
    }

    $raw = $this->value();

    // The config is often stored as a JSON string (e.g. ["entity:node/123"]) but
    // may also be a plain string. Support both.
    $decoded = is_string($raw) ? json_decode($raw, TRUE) : NULL;

    $items = [];
    if (is_array($decoded)) {
      $items = $decoded;
    }
    elseif (is_string($raw) && trim($raw) !== '') {
      $items = [trim($raw)];
    }

    if (empty($items)) {
      return $element;
    }

    $urls = [];
    foreach ($items as $item) {
      // Historically we stored arrays like [{"url":"..."}] or [{"uri":"..."}].
      if (is_array($item)) {
        $candidate = $item['url'] ?? $item['uri'] ?? '';
      }
      else {
        $candidate = (string) $item;
      }

      $candidate = trim($candidate);
      if ($candidate === '') {
        continue;
      }

      $absolute = $this->toAbsoluteUrl($candidate);
      if ($absolute !== '') {
        $urls[] = $absolute;
      }
    }

    if (!empty($urls)) {
      // Even though the tag is marked multiple = FALSE, schema_metatag accepts
      // an array and will output JSON-LD arrays when appropriate.
      $element['#attributes']['content'] = $urls;
    }

    return $element;
  }

  /**
   * Convert an entity/internal URI or URL string to an absolute URL.
   */
  private function toAbsoluteUrl(string $value): string {
    // Already an absolute URL.
    if (preg_match('/^https?:\/\//i', $value)) {
      return $value;
    }

    // Handle Drupal URIs like entity:node/123 and internal:/path.
    try {
      return Url::fromUri($value, ['absolute' => TRUE])->toString();
    }
    catch (\Throwable $e) {
      // Fall through.
    }

    // As a last resort, return empty so we skip invalid values.
    return '';
  }

}

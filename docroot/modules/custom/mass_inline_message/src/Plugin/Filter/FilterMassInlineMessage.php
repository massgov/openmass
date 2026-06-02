<?php

namespace Drupal\mass_inline_message\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Renders mass-inline-message elements using Mayflower inline-message.
 *
 * @Filter(
 *   id = "filter_mass_inline_message",
 *   title = @Translation("Render message boxes"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterMassInlineMessage extends FilterBase {

  /**
   * Allowed HTML tags inside message body.
   */
  public const ALLOWED_BODY_TAGS = [
    'p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li',
  ];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (stripos($text, '<mass-inline-message') === FALSE) {
      return new FilterProcessResult($text);
    }

    $pattern = '#<mass-inline-message\s+([^>]+)>(.*?)</mass-inline-message>#si';
    $output = preg_replace_callback($pattern, function (array $matches) {
      $attributes = $this->parseAttributes($matches[1]);
      $title = trim($attributes['data-title'] ?? '');
      $type = $attributes['data-type'] ?? 'info';
      if (!in_array($type, ['info', 'warning'], TRUE)) {
        $type = 'info';
      }

      $body_html = mass_inline_message_normalize_body_html($matches[2]);
      $body_for_render = $body_html !== '' ? $body_html : NULL;

      // Layout Paragraphs preview renders via Ajax and may skip the global
      // SVG placeholder processor; inline the icon SVGs here for consistency.
      return mass_inline_message_render_html($type, $title, $body_for_render, TRUE);
    }, $text);

    return new FilterProcessResult($output ?? $text);
  }

  /**
   * Parses HTML attributes from a tag attribute string.
   *
   * @param string $attribute_string
   *   Raw attributes, e.g. 'data-title="Foo" data-type="warning"'.
   *
   * @return array
   *   Associative array of attribute name => value.
   */
  protected function parseAttributes(string $attribute_string): array {
    $attributes = [];
    if (preg_match_all('/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $attribute_string, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $attributes[$match[1]] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
      }
    }
    return $attributes;
  }

}

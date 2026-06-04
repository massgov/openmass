<?php

namespace Drupal\mass_inline_message;

/**
 * Normalizes message box body HTML from CKEditor and stored markup.
 */
final class MessageBoxBody {

  /**
   * Unwraps a single CKEditor-exported div wrapper around message body HTML.
   */
  public static function unwrapCkeditorDiv(string $body_html): string {
    $body_html = trim($body_html);
    if ($body_html === '') {
      return '';
    }

    if (preg_match('#^<div[^>]*>(.*)</div>$#is', $body_html, $matches)) {
      $inner = trim($matches[1]);
      if ($inner !== '') {
        return $inner;
      }
    }

    return $body_html;
  }

  /**
   * Extracts raw message body HTML from a mass-inline-message element.
   */
  public static function extractRawFromElement(\DOMElement $node): string {
    $body_html = '';
    foreach ($node->childNodes as $child) {
      $body_html .= $node->ownerDocument->saveHTML($child);
    }
    return self::unwrapCkeditorDiv($body_html);
  }

  /**
   * Extracts and normalizes message body HTML from a mass-inline-message element.
   */
  public static function extractFromElement(\DOMElement $node): string {
    return self::normalize(self::extractRawFromElement($node));
  }

  /**
   * Normalizes message body HTML for validation and rendering.
   */
  public static function normalize(string $body_html): string {
    $body_html = self::unwrapCkeditorDiv($body_html);
    if (!self::hasRenderableContent($body_html)) {
      return '';
    }
    return $body_html;
  }

  /**
   * Whether body HTML has plain-text content worth passing to the theme.
   *
   * CKEditor "empty" bodies are often "<p></p>", "<p><br></p>", or "&nbsp;"
   * which are non-empty strings but should not enable richText in the template.
   */
  public static function hasRenderableContent(?string $body_html): bool {
    if ($body_html === NULL || $body_html === '') {
      return FALSE;
    }
    return self::plainText($body_html) !== '';
  }

  /**
   * Extracts visible plain text from message body HTML.
   */
  public static function plainText(string $body_html): string {
    $plain = html_entity_decode(strip_tags($body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // CKEditor empty states and Drupal filters often leave non-breaking spaces.
    $plain = str_replace(["\xc2\xa0", "\xa0"], ' ', $plain);
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return trim($plain);
  }

}

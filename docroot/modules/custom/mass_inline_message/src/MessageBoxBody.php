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
    if ($body_html === '') {
      return '';
    }
    return $body_html;
  }

}

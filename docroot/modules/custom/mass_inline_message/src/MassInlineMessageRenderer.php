<?php

namespace Drupal\mass_inline_message;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\mayflower\Helper;

/**
 * Builds and renders Mayflower inline-message output for message boxes.
 */
class MassInlineMessageRenderer {

  public function __construct(
    protected RendererInterface $renderer,
  ) {}

  /**
   * Builds a render array for #theme mass_inline_message.
   */
  public function buildRenderArray(string $type, string $heading, ?string $body = NULL): array {
    return [
      '#theme' => 'mass_inline_message',
      '#type' => $type,
      '#heading' => $heading,
      '#body' => MessageBoxBody::hasRenderableContent($body) ? Markup::create($body) : NULL,
    ];
  }

  /**
   * Renders Mayflower inline-message HTML (same output as filter_mass_inline_message).
   *
   * @param bool $inline_svg
   *   When TRUE, replaces svg-placeholder tags with embeds (for CKEditor preview).
   */
  public function renderHtml(string $type, string $heading, ?string $body = NULL, bool $inline_svg = FALSE): string {
    $render_array = $this->buildRenderArray($type, $heading, $body);
    $html = (string) $this->renderer->renderPlain($render_array);
    if ($inline_svg) {
      $html = $this->processSvgPlaceholders($html);
    }
    return $html;
  }

  /**
   * Inlines Mayflower SVG placeholders for contexts without SvgProcessor.
   */
  public function processSvgPlaceholders(string $html): string {
    if (strpos($html, '<svg-placeholder') === FALSE) {
      return $html;
    }

    $inlined = [];
    preg_match_all('/<svg-placeholder\s+path="([^"]*)"(?:[^>]*width="([^"]*)")?(?:[^>]*height="([^"]*)")?(?:[^>]*class="([^"]*)")?[^>]*>/', $html, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $path = $match[1];
      $dimensions = array_filter([
        'width' => $match[2] ?? '',
        'height' => $match[3] ?? '',
        'class' => $match[4] ?? '',
      ]);

      $svg_node = Helper::getSvg($path);
      if (!$svg_node) {
        continue;
      }

      $hash = md5($path);
      if (!$dimensions) {
        if ($svg_node->hasAttribute('width')) {
          $dimensions['width'] = $svg_node->getAttribute('width');
        }
        if ($svg_node->hasAttribute('height')) {
          $dimensions['height'] = $svg_node->getAttribute('height');
        }
      }

      $svg_node->setAttribute('id', $hash);
      $replacement = Helper::getSvgEmbed($hash, $dimensions);
      $inlined[] = Helper::getSvgSource($hash, $svg_node);
      $html = preg_replace(
        sprintf('/<svg-placeholder\s+path="%s"[^>]*>/', preg_quote($path, '/')),
        $replacement,
        $html
      );
    }

    if ($inlined) {
      $html .= Helper::wrapInlinedSvgs($inlined);
    }

    return $html;
  }

}

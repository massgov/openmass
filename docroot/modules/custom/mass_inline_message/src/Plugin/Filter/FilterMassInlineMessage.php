<?php

namespace Drupal\mass_inline_message\Plugin\Filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\mass_inline_message\MassInlineMessageRenderer;
use Drupal\mass_inline_message\MessageBoxBody;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders mass-inline-message elements using Mayflower inline-message.
 *
 * @Filter(
 *   id = "filter_mass_inline_message",
 *   title = @Translation("Render message boxes"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterMassInlineMessage extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MassInlineMessageRenderer $messageRenderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mass_inline_message.renderer'),
    );
  }

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

      $body_html = MessageBoxBody::normalize($matches[2]);
      $body_for_render = $body_html !== '' ? $body_html : NULL;

      // Layout Paragraphs preview renders via Ajax and may skip the global
      // SVG placeholder processor; inline the icon SVGs here for consistency.
      return $this->messageRenderer->renderHtml($type, $title, $body_for_render, TRUE);
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

<?php

declare(strict_types=1);

namespace Drupal\mass_inline_message\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 plugin for Mass Inline Message (Message box).
 *
 * @CKEditor5Plugin(
 *   id = "mass_inline_message_message_box",
 *   ckeditor5 = @CKEditor5AspectsOfCKEditor5Plugin(
 *     plugins = { "mass_inline_message.MassInlineMessage" },
 *   ),
 *   drupal = @DrupalAspectsOfCKEditor5Plugin(
 *     label = @Translation("Message box"),
 *     library = "mass_inline_message/ckeditor5.mass_inline_message",
 *     toolbar_items = {
 *       "messageBox" = {
 *         "label" = @Translation("Message box"),
 *       },
 *     },
 *     elements = {
 *       "<mass-inline-message>",
 *       "<mass-inline-message data-title data-type>",
 *       "<p>",
 *       "<br>",
 *       "<strong>",
 *       "<em>",
 *       "<a href hreflang>",
 *       "<ul>",
 *       "<ol>",
 *       "<li>",
 *     },
 *   ),
 * )
 */
class MassInlineMessage extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dynamic_plugin_config = $static_plugin_config;
    $format_id = $editor->getFilterFormat()->id();
    $dynamic_plugin_config['massInlineMessage'] = [
      'format' => $format_id,
      'previewUrl' => Url::fromRoute('mass_inline_message.preview', [
        'filter_format' => $format_id,
      ])->toString(),
      'toolbar' => [
        'massInlineMessageEdit',
      ],
      'dialogSettings' => [
        'dialogClass' => 'mass-inline-message-dialog',
        'height' => 'auto',
        'width' => 'auto',
      ],
    ];
    return $dynamic_plugin_config;
  }

}

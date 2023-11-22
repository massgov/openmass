<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldFormatter\LinkSeparateFormatter;

/**
 * Plugin implementation of the 'class_link' formatter.
 *
 * @FieldFormatter(
 *   id = "mass_link_title_separated",
 *   label = @Translation("Separate internal link title and URL"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkTitleSeparateFormatter extends LinkSeparateFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = parent::viewElements($items, $langcode);
    $settings = $this->getSettings();

    // Update each link title to node label.
    foreach ($element as $page_key => $page_link) {
      if (empty($page_link['#title'])) {
        // Build node label from the raw uri as elements values can already
        // be formatted as trimmed.
        $uri = $items->getValue()[$page_key]['uri'];
        $url = Url::fromUri($uri);
        $node_label = '';

        if ($url->isRouted() && $nid = $url->getRouteParameters()['node']) {
          if ($node = \Drupal::entityTypeManager()->getStorage('node')->load($nid)) {
            $node_label = $node->label();
          }
          else {
            $element[$page_key]['#access'] = FALSE;
          }
        }
        else {
          $node_label = $element[$page_key]['#url_title'];
        }

        // Set link title to node label or the trimmed format.
        $element[$page_key]['#title'] = (!empty($settings['trim_length'])) ? Unicode::truncate($node_label, $settings['trim_length'], FALSE, TRUE) : $node_label;
      }
    }

    return $element;
  }

}

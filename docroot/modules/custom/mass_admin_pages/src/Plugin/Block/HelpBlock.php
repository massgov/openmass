<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a block for the intro text on the node add page.
 */
#[Block(
  id: 'help_support_block',
  admin_label: new TranslatableMarkup('Help and support'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class HelpBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    \Drupal::state()->get('mass_admin_pages.help_block_settings', []);
    $text_field = \Drupal::state()->get('mass_admin_pages.help_block_settings.text_field');
    $link_title = \Drupal::state()->get('mass_admin_pages.help_block_settings.link_title');
    $link_url = \Drupal::state()->get('mass_admin_pages.help_block_settings.link_url');

    $buildInfo = [];
    if (!empty($text_field)) {
      $buildInfo['text_field'] = [
        '#markup' => Xss::filterAdmin($text_field),
      ];
    }

    if (!empty($link_title) && !empty($link_url)) {
      $buildInfo['link_field'] = [
        '#title' => Html::escape($link_title),
        '#type' => 'link',
        '#url' => Url::fromUri(UrlHelper::filterBadProtocol($link_url)),
        '#attributes' => [
          'class' => [
            'button',
            'button--see-help-articles',
          ],
        ],
      ];
    }

    return $buildInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'state:mass_admin_pages.help_block_settings';
    return $cache_tags;
  }

}

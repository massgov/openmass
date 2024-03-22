<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block for the intro text on the node add page.
 */
#[Block(
  id: 'updates_block',
  admin_label: new TranslatableMarkup('Updates'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class UpdatesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    \Drupal::state()->get('mass_admin_pages.updates_block_settings', []);
    $text_field = \Drupal::state()->get('mass_admin_pages.updates_block_settings.text_field');
    $buildInfo = [];
    if (!empty($text_field)) {
      $buildInfo['text_field'] = [
        '#markup' => Xss::filterAdmin($text_field),
      ];
    }

    return $buildInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'state:mass_admin_pages.updates_block_settings';
    return $cache_tags;
  }

}

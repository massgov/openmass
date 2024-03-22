<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block for the rigth sidebar text.
 */
#[Block(
  id: 'right_sidebar_block',
  admin_label: new TranslatableMarkup('Right Sidebar'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class RightSidebarBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $text_field = \Drupal::state()->get('mass_admin_pages.right_sidebar_block_settings.text_field');
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
    $cache_tags[] = 'state:mass_admin_pages.right_sidebar_block_settings';
    return $cache_tags;
  }

}

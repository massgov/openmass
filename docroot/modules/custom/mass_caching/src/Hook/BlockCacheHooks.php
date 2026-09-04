<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for block cacheability.
 */
class BlockCacheHooks {

  /**
   * Disable caching of local tasks.
   *
   * @see https://massgov.atlassian.net/browse/DP-33081
   */
  #[Hook('block_view_alter')]
  public function blockViewAlter(array &$build, BlockPluginInterface $block): void {
    if ($build['#id'] === 'mass_theme_tabs') {
      $build['#cache']['max-age'] = 0;
    }
  }

}

<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for the intro text on the node add page.
 *
 * @Block(
 *   id = "alerts_block",
 *   admin_label = @Translation("Alerts")
 * )
 */
class AlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = \Drupal::currentUser();

    $alert_text = \Drupal::state()->get('mass_admin_pages.updates_block_settings.alert_text');
    $buildInfo = [];

    if ($user->hasPermission('view alerts block')) {
      if (!empty($alert_text)) {
        $buildInfo['alert_text'] = [
          '#markup' => Xss::filterAdmin($alert_text),
        ];
      }
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

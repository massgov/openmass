<?php

namespace Drupal\mass_content\Field;

use Drupal\mayflower\Helper;

/**
 * A computed field class for social links.
 */
class SocialLinks extends InjectParentField {

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    // Get icons for social links.
    $services = [
      'twitter',
      'facebook',
      'threads',
      'flickr',
      'blog',
      'linkedin',
      'google',
      'instagram',
      'medium',
      'youtube',
      'bluesky',
    ];
    parent::computeValue();

    foreach ($this->list as &$item) {
      foreach ($services as $service) {
        if (strpos($item->uri, $service)) {
          $item->set('icon', Helper::getIconPath($service));
          break;
        }
      }
      if (strpos($item->uri, 'bsky')) {
        $item->set('icon', Helper::getIconPath('bluesky'));
        break;
      }
      elseif (strpos($item->uri, 'x.com')) {
        $item['icon'] = Helper::getIconPath('twitter');
      }
    }
  }

}

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
    ];
    parent::computeValue();

    foreach ($this->list as &$item) {
      foreach ($services as $service) {
        if (strpos($item->uri, $service)) {
          $item->set('icon', Helper::getIconPath($service));
          break;
        }
      }
    }
  }

}

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
    // Map social URL domains to mayflower icon names (e.g. x-logo, facebook-logo).
    // Must match the icon filenames in mayflower assets so getIconPath() finds them.
    // Icon names were updated in DP-39285 (e.g. twitter to x-logo, bluesky to bluesky-logo).
    $domain_to_icon = [
      'twitter.com' => 'x-logo',
      'x.com' => 'x-logo',
      'facebook.com' => 'facebook-logo',
      'threads.net' => 'threads-logo',
      'threads.com' => 'threads-logo',
      'flickr.com' => 'flickr-logo',
      'linkedin.com' => 'linkedin-logo',
      'instagram.com' => 'instagram-logo',
      'medium.com' => 'medium-logo',
      'youtube.com' => 'youtube-logo',
      'vimeo.com' => 'vimeo-logo',
      'tiktok.com' => 'tiktok-logo',
      'bsky.app' => 'bluesky-logo',
      'bluesky.social' => 'bluesky-logo',
    ];
    parent::computeValue();

    foreach ($this->list as &$item) {
      $icon_set = FALSE;
      foreach ($domain_to_icon as $domain => $icon_name) {
        if (strpos($item->uri, $domain) !== FALSE) {
          $item->set('icon', Helper::getIconPath($icon_name));
          $icon_set = TRUE;
          break;
        }
      }
      if (!$icon_set && strpos($item->uri, 'bsky') !== FALSE) {
        $item->set('icon', Helper::getIconPath('bluesky-logo'));
      }
    }
  }

}

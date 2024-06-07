<?php

namespace Drupal\mass_translations;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageManager;

class MassLanguageManager extends ConfigurableLanguageManager {

  public static function getStandardLanguageList() {
    $standard = parent::getStandardLanguageList();
    $extra = [
      'cv' => ['Cape Verdean Creole', 'Kriolu di Cabo Verde'],
      'hmn' => ['Hmong', 'Lus Hmoob'],
      'pst' => ['Pashto', 'پښتو'],
      'prs' => ['Dari', 'دری'],
      'so' => ['Somali', 'Soomaali'],
      'tw' => ['Twi', 'Twi'],
    ];
    $list = array_merge($extra, $standard);
    ksort($list);
    return $list;
  }

  /**
   * Fix Revisions UI for media.
   *
   * @see https://massgov.atlassian.net/browse/DP-31592https://massgov.atlassian.net/browse/DP-31592
   */
  public function getCurrentLanguage($type = LanguageInterface::TYPE_INTERFACE) {
    if ($type == LanguageInterface::TYPE_CONTENT) {
      $media = \Drupal::routeMatch()->getParameter('media');
      if ($media) {
        return $media->language();
      }
    }
    // Fallback to parent.
    return parent::getCurrentLanguage($type);
  }

}

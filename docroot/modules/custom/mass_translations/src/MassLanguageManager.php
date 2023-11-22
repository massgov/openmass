<?php

namespace Drupal\mass_translations;

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

}

<?php

namespace Drupal\mass_translations;

use Drupal\language\ConfigurableLanguageManager;

class MassLanguageManager extends ConfigurableLanguageManager {

  public static function getStandardLanguageList() {
    $standard = parent::getStandardLanguageList();
    $extra = [
      'cv' => ['Cape Verdean Creole', 'Kriolu di Cabo Verde'],
      'pst' => ['Pashto', 'پښتو'],
      'prs' => ['Dari', 'دری'],
      'so' => ['Somali', 'Soomaali'],
    ];
    $list = array_merge($extra, $standard);
    ksort($list);
    return $list;
  }

}

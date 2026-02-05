<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mass_metatag_time' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_time",
 *   label = @Translation("mg_time"),
 *   description = @Translation("The time for this page."),
 *   name = "mg_time",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagTime extends MetaNameBase {

  use MassMetatagFallbackFieldTrait;

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if (empty($element)) {
      $element = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'mg_time',
        ],
      ];
    }

    $value = $this->value();
    if (!$value && $fallback_field_value = $this->getFallbackFieldValue('daterange')) {
      $value = $fallback_field_value;
    }

    $element['#attributes']['content'] = $value;
    return $element;
  }

}

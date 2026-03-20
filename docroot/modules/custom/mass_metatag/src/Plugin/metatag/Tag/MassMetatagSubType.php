<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mass_metatag_sub_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "mass_metatag_sub_type",
 *   label = @Translation("mg_sub_type"),
 *   description = @Translation("The subtype of page."),
 *   name = "mg_sub_type",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagSubType extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if (!empty($element['#attributes']['content'])) {
      $element['#attributes']['content'] =
        _mass_metatag_slugify($element['#attributes']['content']);
    }
    return $element;
  }

}

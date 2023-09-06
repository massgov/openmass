<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'AnnouncementLocation' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_location",
 *   label = @Translation("announcementLocation"),
 *   description = @Translation("Is there a specific Location that this involves?"),
 *   name = "announcementLocation",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "place",
 *   tree_parent = {
 *     "Place",
 *   },
 *   tree_depth = 2,
 * )
 */
class SchemaSpecialAnnouncementAnnouncementLocation extends SchemaNameBase {

  /**
   * Add AnnouncementLocation property options.
   */
//  public function form($element = []) {
//    $form = parent::form($element);
//    $form['@type']['#options'] = [
//      'CivicStructure' => $this->t('CivicStructure'),
//      'LocalBusiness' => $this->t('LocalBusiness'),
//    ];
//    $value = SchemaMetatagManager::unserialize($this->value());
//
//    $image_input_values = [
//      'title' => $this->t('Image'),
//      'description' => $this->t('An Image of the location'),
//      'value' => !empty($value['image']) ? $value['image'] : [],
//      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
//      'visibility_selector' => $this->visibilitySelector(),
//    ];
//    $form['image'] = $this->form($image_input_values);
//    $form['image']['@type']['#states']['required'] = $form['image']['url']['#states']['required'] = [
//      ':input[name="' . $this->visibilitySelector() . '[@type]"]' => ['value' => 'LocalBusiness'],
//    ];
//
//    return $form;
//  }

}

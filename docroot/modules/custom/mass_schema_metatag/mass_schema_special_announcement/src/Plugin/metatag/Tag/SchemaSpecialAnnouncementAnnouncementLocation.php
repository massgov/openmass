<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageTrait;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPlaceBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'SpatialCoverage' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_location",
 *   label = @Translation("Announcement Location"),
 *   description = @Translation("Is there a specific Location that this involves?"),
 *   name = "announcementLocation",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE,
 * )
 */
class SchemaSpecialAnnouncementAnnouncementLocation extends SchemaPlaceBase {
  use SchemaImageTrait;

  /**
   * @inheritdoc
   */
  public function form($element = []) {
    $form = parent::form($element);
    $form['@type']['#options'] = [
      'CivicStructure' => $this->t('CivicStructure'),
      'LocalBusiness' => $this->t('LocalBusiness'),
    ];
    $value = SchemaMetatagManager::unserialize($this->value());

    $image_input_values = [
      'title' => $this->t('Image'),
      'description' => $this->t('An Image of the location'),
      'value' => !empty($value['image']) ? $value['image'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];
    $form['image'] = $this->imageForm($image_input_values);
    $form['image']['@type']['#states']['required'] = $form['image']['url']['#states']['required'] = [
      ':input[name="' . $this->visibilitySelector() . '[@type]"]' => ['value' => 'LocalBusiness'],
    ];

    return $form;
  }
}


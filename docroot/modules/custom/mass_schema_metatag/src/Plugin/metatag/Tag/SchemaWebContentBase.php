<?php

namespace Drupal\mass_schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaCreativeWorkBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaActionBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org CreativeWork items should extend this class.
 */
class SchemaWebContentBase extends SchemaCreativeWorkBase {

  use SchemaWebContentTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {

    $value = SchemaMetatagManager::unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];

    $form = $this->creativeWorkForm($input_values);

    if (empty($this->multiple())) {
      unset($form['pivot']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = self::creativeWorkFormKeys('WebContent');
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'WebContent';
          break;

        case 'author':
          $items[$key] = SchemaPersonOrgBase::testValue();
          break;

        case 'potentialAction':
          $items[$key] = SchemaActionBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'author':
          $items[$key] = SchemaPersonOrgBase::processedTestValue($items[$key]);
          break;

        case 'potentialAction':
          $items[$key] = SchemaActionBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}

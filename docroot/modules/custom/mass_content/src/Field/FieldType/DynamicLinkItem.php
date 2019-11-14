<?php

namespace Drupal\mass_content\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * An overriding class for link items that provides a 'computed_title' property.
 */
class DynamicLinkItem extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['computed_title'] = DataDefinition::create('string')
      ->setLabel('Computed Title')
      ->setComputed(TRUE)
      ->setClass('Drupal\mass_content\ComputedLinkTitle');
    $properties['computed_description'] = DataDefinition::create('string')
      ->setLabel('Computed Description')
      ->setComputed(TRUE)
      ->setClass('Drupal\mass_content\ComputedLinkDescription');
    $properties['computed_date'] = DataDefinition::create('string')
      ->setLabel('Computed Date')
      ->setComputed(TRUE)
      ->setClass('Drupal\mass_content\ComputedLinkDate');
    $properties['computed_type'] = DataDefinition::create('string')
      ->setLabel('Computed Type')
      ->setComputed(TRUE)
      ->setClass('Drupal\mass_content\ComputedLinkType');

    return $properties;
  }

}

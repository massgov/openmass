<?php

namespace Drupal\mass_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a field type of baz.
 *
 * @FieldType(
 *   id = "form_embed",
 *   label = @Translation("Form Embed field"),
 *   default_formatter = "form_embed",
 *   default_widget = "form_embed",
 * )
 */
class FormEmbedItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'type' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'value' => [
          'type' => 'text',
          'size' => 'big',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Form Type'));

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Embed Type'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}

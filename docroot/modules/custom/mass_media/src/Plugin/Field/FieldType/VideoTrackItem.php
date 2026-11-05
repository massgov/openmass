<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * A managed WebVTT file with its track language and optional display label.
 */
#[FieldType(
  id: 'mass_media_video_track',
  label: new TranslatableMarkup('Video text track'),
  description: new TranslatableMarkup('A caption or chapter file with a language and label.'),
  category: 'file_upload',
  default_widget: 'mass_media_video_track',
  default_formatter: 'mass_media_video_track',
  list_class: FileFieldItemList::class,
  constraints: ['ReferenceAccess' => [], 'FileValidation' => []],
)]
final class VideoTrackItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['track_language'] = [
      'description' => 'The language of the text track.',
      'type' => 'varchar',
      'length' => 12,
      'default' => 'en',
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['track_language'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Track language'))
      ->setRequired(TRUE);
    return $properties;
  }

}

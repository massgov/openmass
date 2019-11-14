<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

/**
 * Provides methods to get fallback field values.
 */
trait MassMetatagFallbackFieldTrait {

  /**
   * Retrieve the value from a fallback field for a given content type.
   *
   * @return string
   *   The value from the fallback field.
   */
  public function getFallbackFieldValue($field_type = '') {
    $value = '';
    // Get the current entity and its type.
    if ($entity = metatag_get_route_entity()) {
      $content_type = $entity->getType();
      $fallback_fields = $this->getFallbackFields($content_type);
      // Iterate through each fallback field specified for the content type.
      foreach ($fallback_fields as $field_info) {
        // If there is no field specified, move to the next one.
        if (!isset($field_info['field'])) {
          continue;
        }

        $field_name = $field_info['field'];
        // If there is no field value for the fallback field, move onto the
        // next one.
        if (!$entity->hasField($field_name) || empty($entity->get($field_name)->getValue())) {
          continue;
        }

        // If a paragraph field was specified and is a paragraph reference
        // field, process logic to get the values from each paragraph.
        if (!empty($field_info['field_on_paragraph']) && $entity->{$field_name}->getFieldDefinition()->getType() == 'entity_reference_revisions') {
          $values = [];
          $paragraph_field_name = $field_info['field_on_paragraph'];

          // Get all the paragraph entities referenced on the field.
          $paragraphs = $entity->{$field_name}->referencedEntities();
          // For each paragraph, get the value from the paragraph field.
          foreach ($paragraphs as $paragraph) {
            if ($paragraph->hasField($paragraph_field_name)) {
              // Get the field type on the paragraph.
              $paragraph_field_type = $paragraph->get($paragraph_field_name)
                ->getFieldDefinition()
                ->getType();
              // If a field type is specified, only process further for fields
              // that match that type.
              if ($field_type && $field_type !== $paragraph_field_type) {
                continue;
              }
              $values[] = trim($paragraph->{$paragraph_field_name}->value);
            }
          }

          // Concatenate all values separated by a space.
          $value = implode(' ', $values);
        }
        // If the field type is given as a daterange, get the value of the
        // datetime field and extract the time value.
        elseif ($field_type == 'daterange') {
          $date_object = $entity->get($field_name)->start_date;
          if (!empty($date_object)) {
            $hour = $date_object->format('g');
            $minute = $date_object->format('i');
            $value = $hour;
            // Exclude the minute value if it is '00'.
            if ($minute !== '00') {
              $value .= ':' . $minute;
            }
            // Get the meridian and reformat to contain periods.
            $meridian = $date_object->format('a');
            $meridian = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $meridian);
            $value .= ' ' . $meridian;
          }
        }
        // Otherwise, just get the field value.
        else {
          $entity_field_type = $entity->get($field_name)->getFieldDefinition()->getType();
          // If a field type is specified, only process further for fields that
          // match that type.
          if ($field_type && $field_type !== $entity_field_type) {
            continue;
          }
          $value = $entity->{$field_name}->value;
        }

        // Remove all tags from the values.
        $value = strip_tags($value);

        // Break because we found a value from a fallback field.
        break;
      }
    }

    return $value;
  }

  /**
   * Retrieve an array of fallback fields by content type.
   *
   * @param string $content_type
   *   The content type to return fallback fields for.
   *
   * @return array
   *   The array of fallback fields.
   */
  public function getFallbackFields($content_type) {
    // Mapping of fallback fields by content type.
    $fallback_field_mapping = [
      'advisory' => [
        [
          'field' => 'field_advisory_section',
          'field_on_paragraph' => 'field_advisory_section_body',
        ],
      ],
      'decision' => [
        [
          'field' => 'field_decision_section',
          'field_on_paragraph' => 'field_decision_section_body',
        ],
      ],
      'event' => [
        [
          'field' => 'field_event_date',
        ],
        [
          'field' => 'field_event_description',
        ],
      ],
      'location_details' => [
        [
          // The field on the content type.
          'field' => 'field_location_details_sections',
          // The field on the paragraph.
          'field_on_paragraph' => 'field_section_body',
        ],
      ],
      'regulation' => [
        [
          'field' => 'field_regulation_section',
          'field_on_paragraph' => 'field_regulation_section_body',
        ],
      ],
    ];

    if (!isset($fallback_field_mapping[$content_type])) {
      return [];
    }
    return $fallback_field_mapping[$content_type];
  }

}

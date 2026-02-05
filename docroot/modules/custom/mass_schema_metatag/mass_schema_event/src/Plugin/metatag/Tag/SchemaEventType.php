<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_type' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of event."),
 *   name = "@type",
 *   group = "schema_event",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventType extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Event' => $this->t('Event'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}

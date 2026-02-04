<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\node\Entity\Node;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_government_service_related_services' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_related_services",
 *   label = @Translation("isRelatedTo"),
 *   description = @Translation("The related services."),
 *   name = "isRelatedTo",
 *   group = "schema_government_service",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaGovernmentServiceRelatedServices extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value): void
  {
    // Metatag can provide NULL (no defaults yet) or an array when `multiple=TRUE`.
    // Normalize to a string so ::value() never returns NULL (strict typing in D11).
    if ($value === NULL) {
      $this->value = '';
      return;
    }

    if (is_array($value)) {
      // Join multiple values into a single comma-separated string.
      $value = array_values(array_filter($value, static fn($v) => $v !== NULL && $v !== ''));
      $this->value = $value ? implode(', ', array_map('strval', $value)) : '';
      return;
    }

    $this->value = (string) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();

    // Ensure we always have a string to work with.
    $value = $this->value();
    if (is_array($value)) {
      $value = implode(', ', $value);
    }
    $value = trim((string) $value);

    if ($value === '') {
      return $element;
    }

    $content = array_map('trim', explode(',', $value));

    if (!empty($element) && is_array($content)) {
      $element['#attributes']['content'] = [];

      // Iterate through each target id and get the url of each node to
      // reference as a related service.
      foreach ($content as $target_id) {
        $target_id = (int) $target_id;
        if ($target_id <= 0) {
          continue;
        }
        $node = Node::load($target_id);
        if (!$node) {
          continue;
        }
        $element['#attributes']['content'][] = [
          '@type' => 'Service',
          '@id' => $node->toUrl('canonical', ['absolute' => TRUE])->toString() . '#services',
        ];
      }
    }
    return $element;
  }

}

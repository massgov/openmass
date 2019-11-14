<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_government_service_related_link' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_related_link",
 *   label = @Translation("isRelatedTo"),
 *   description = @Translation("A related link."),
 *   name = "isRelatedTo",
 *   group = "schema_government_service",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaGovernmentServiceRelatedLink extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_url]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    $links = json_decode($this->value(), TRUE);

    if (!empty($element) && is_array($links)) {
      $element['#attributes']['content'] = [];

      // Iterate through each link to get the url.
      foreach ($links as $link) {
        $element['#attributes']['content'][] = [
          '@type' => 'Service',
          'name' => !empty($link['name']) ? $link['name'] : '',
          'url' => !empty($link['url']) ? $link['url'] : '',
        ];
      }
    }
    return $element;
  }

}

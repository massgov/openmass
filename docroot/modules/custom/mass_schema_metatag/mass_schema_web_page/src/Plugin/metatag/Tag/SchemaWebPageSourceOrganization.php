<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\node\Entity\Node;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_source_organization' metatag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_source_organization",
 *   label = @Translation("sourceOrganization"),
 *   description = @Translation("The location depicted or described in the content. For example, the location in a photograph or painting."),
 *   name = "sourceOrganization",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageSourceOrganization extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_ref_organization]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    if (!empty($element)) {
      $values = NULL;
      $value = $this->value();
      if (is_numeric($value)) {
        $node = Node::load($value);
        if ($node) {
          $value = $node->getTitle();
        }
      }

      $element['#attributes']['content'] = $value;
    }

    return $element;
  }

}

<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_reviewed_by' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_reviewed_by",
 *   label = @Translation("reviewedBy"),
 *   description = @Translation("People or organizations that have reviewed the content on this web page for accuracy and/or completeness."),
 *   name = "reviewedBy",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageReviewedBy extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_participants]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    if (!empty($element)) {
      $element['#attributes']['content'] = [];

      $element['#attributes']['content'][] = json_decode($this->value());
      ;
    }
    return $element;
  }

}

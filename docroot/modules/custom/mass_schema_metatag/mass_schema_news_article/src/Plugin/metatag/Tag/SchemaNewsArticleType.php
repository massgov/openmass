<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_news_article_type' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of article."),
 *   name = "@type",
 *   group = "schema_news_article",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaNewsArticleType extends SchemaNameBase {

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
        'NewsArticle' => $this->t('NewsArticle'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}

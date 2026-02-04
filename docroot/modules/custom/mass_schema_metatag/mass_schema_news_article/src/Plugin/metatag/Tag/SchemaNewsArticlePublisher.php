<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_news_article_publisher' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_publisher",
 *   label = @Translation("publisher"),
 *   description = @Translation("The publisher of the creative work."),
 *   name = "publisher",
 *   group = "schema_news_article",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaNewsArticlePublisher extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();
    if (!empty($element)) {
      $element['#attributes']['content'] = [];
      $relative_path = '';
      // Get the favicon path from the configuration settings.
      if (!empty(\Drupal::config('mass_theme.settings')->get('favicon')['path'])) {
        $relative_path = \Drupal::config('mass_theme.settings')
          ->get('favicon')['path'];
      }
      // Generate the favicon image path.
      $logo_path = \Drupal::request()->getSchemeAndHttpHost() . '/' . $relative_path;

      $element['#attributes']['content'][] = [
        '@type' => 'Organization',
        'name' => $this->value(),
        'logo' => [
          '@type' => 'ImageObject',
          'url' => $logo_path,
        ],
      ];
    }

    return $element;
  }

}

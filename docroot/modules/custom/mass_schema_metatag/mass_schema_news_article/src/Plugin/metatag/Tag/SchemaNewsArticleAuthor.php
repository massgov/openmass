<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for 'schema_news_article_author' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_news_article_author",
 *   label = @Translation("author"),
 *   description = @Translation("The author of this content or rating. Please note that author is special in that HTML 5 provides a special mechanism for indicating authorship via the rel tag. That is equivalent to this and may be used interchangeably."),
 *   name = "author",
 *   group = "schema_news_article",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "organization",
 *   tree_parent = {
 *     "Person",
 *     "Organization",
 *   },
 *   tree_depth = 0
 * )
 */
class SchemaNewsArticleAuthor extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['name']['#attribute']['placeholder'] = '[node:author:display-name]';
    $form['url']['#attributes']['placeholder'] = '[node:author:url]';
    unset($form['url']);
    unset($form['sameAs']);
    unset($form['logo']);
    unset($form['@id']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    if (!empty($element)) {
      $element['#attributes']['content'] = [];
      $values = SchemaMetatagManager::unserialize($this->value());
      $names = (array) json_decode($values['name']);
      foreach ($names as $name) {
        $element['#attributes']['content'][] = [
          '@type' => isset($values['@type']) ? $values['@type'] : 'Thing',
          'name' => $name,
        ];
      }
    }
    return $element;
  }

}

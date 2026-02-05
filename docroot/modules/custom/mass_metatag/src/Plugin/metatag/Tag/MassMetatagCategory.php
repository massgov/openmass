<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mass_metatag_category' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "mass_metatag_category",
 *   label = @Translation("category"),
 *   description = @Translation("The category of the page."),
 *   name = "category",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagCategory extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    // @todo This should replace category in mass_theme_preprocess_html.
    $element = parent::output();
    $node = \Drupal::routeMatch()->getParameter('node');
    // For Binder nodes, only show category for binder type law library.
    if ($node instanceof NodeInterface && $node->bundle() == 'binder') {
      if ($binder = $node->get('field_binder_binder_type')) {
        if ($binder_ref = current($binder->referencedEntities())) {
          $binder_type = $binder_ref->get('name')->first()->getValue();
          // Add category when binder type is not "law library" with data flag on.
          if ($binder_type['value'] !== 'Law Library') {
            if (!empty($node->get('field_data_flag')->getValue())) {
              $tag = [
                '#tag' => 'meta',
                '#attributes' => [
                  'name' => 'category',
                  'content' => 'data',
                ],
              ];
              return $tag;
            }

            else {
              return [];
            }
          }
        }
      }
    }
    return $element;
  }

}

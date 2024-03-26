<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block for searching/filtering content types on the node add page.
 */
#[Block(
  id: 'add_content_search',
  admin_label: new TranslatableMarkup("Add Content Search"),
  category: new TranslatableMarkup('Mass Admin Pages'),
)]
class AddContentSearch extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Make category terms into links.
    $links = [];
    $categories = _mass_admin_pages_get_used_categories();
    foreach ($categories as $key => $value) {
      $links[] = [
        '#markup' => $this->t('<a href="#:key">@value</a>', [
          ':key' => $key,
          '@value' => $value,
        ]),
      ];
    }

    return [
      [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => 'Jump to a category',
        '#items' => $links,
        '#attributes' => ['class' => 'category-jump-links'],
        '#wrapper_attributes' => ['class' => 'container'],
      ],
    ];
  }

}

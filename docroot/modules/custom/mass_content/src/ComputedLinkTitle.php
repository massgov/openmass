<?php

namespace Drupal\mass_content;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mayflower\Helper;

/**
 * Computes a title for a link based on either the link title/entity title.
 */
class ComputedLinkTitle extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    /** @var \Drupal\link\LinkItemInterface $parent */
    $parent = $this->getParent();
    if (!$parent instanceof DynamicLinkItem) {
      throw new \RuntimeException('Computed title can only be generated for link items');
    }

    // Checks if 'title' is already set on LinkItem.
    // NOTE: 'title' will always be set on external links based on constraints.
    if (!empty($name = $parent->get('title')->getValue())) {
      return $name;
    }
    // Gets label of referenced entity to use as link title.
    elseif ($entity = Helper::entityFromUrl($parent->getUrl())) {
      return [
        '#markup' => $entity->label(),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }
    else {
      return '';
    }
  }

}

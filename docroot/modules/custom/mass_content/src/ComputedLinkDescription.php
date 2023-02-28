<?php

namespace Drupal\mass_content;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mayflower\Helper;

/**
 * Computes a title for a link based on either the link title/entity title.
 */
class ComputedLinkDescription extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    /** @var \Drupal\link\LinkItemInterface $parent */
    $parent = $this->getParent();
    if (!$parent instanceof DynamicLinkItem) {
      throw new \RuntimeException('Computed title can only be generated for link items');
    }
    // Gets label of referenced entity to use as link title.
    if ($entity = Helper::entityFromUrl($parent->getUrl())) {
      $desc_fields = [
        'advisory' => 'field_advisory_listing_desc',
        'binder' => 'field_binder_listing_desc',
        'decision' => 'field_decision_listing_desc',
        'executive_order' => 'field_exec_order_listing_desc',
        'form_page' => 'field_form_listing_desc',
        'info_details' => 'field_info_details_listing_desc',
        'regulation' => 'field_regulation_listing_desc',
        'rules' => 'field_rules_listing_desc',
      ];
      if (isset($desc_fields[$entity->bundle()])) {
        if (!empty($entity->{$desc_fields[$entity->bundle()]}->value)) {
          return [
            '#markup' => $entity->{$desc_fields[$entity->bundle()]}->value,
            '#cache' => [
              'tags' => $entity->getCacheTags(),
            ],
          ];
        }
      }
    }
    return '';
  }

}

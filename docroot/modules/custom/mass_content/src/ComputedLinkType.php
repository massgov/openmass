<?php

namespace Drupal\mass_content;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\field\Entity\FieldConfig;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mayflower\Helper;

/**
 * Computes a type for a link based on a content type to field mapping.
 */
class ComputedLinkType extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    /** @var \Drupal\link\LinkItemInterface $parent */
    $parent = $this->getParent();
    if (!$parent instanceof DynamicLinkItem) {
      throw new \RuntimeException('Computed title can only be generated for link items');
    }

    // Gets entity of the referenced entity from the URL.
    if ($entity = Helper::entityFromUrl($parent->getUrl())) {

      // Sets a mapping of content type to "type" fields.
      $type_fields = [
        'advisory' => 'field_advisory_type_tax',
        'binder' => 'field_binder_binder_type',
        'curated_list' => 'field_list_type',
        'decision' => 'field_decision_ref_type',
        'event' => 'field_event_type_list',
        'executive_order' => 'field_executive_order_adjustment',
        'news' => 'field_news_type',
        'rules' => 'field_rules_type',
      ];

      $type = NULL;

      // Safe-proof against any new content types that get added, so we make
      // sure the entity bundle is in the array in the mapping.
      if (in_array($entity->bundle(), array_keys($type_fields))) {
        if (!empty($entity->{$type_fields[$entity->bundle()]}->value)) {
          $type = $entity->{$type_fields[$entity->bundle()]}->value;
        }
      }

      // Custom handling based on content type.
      switch ($entity->bundle()) {
        case 'regulation':
          // Regulation is different because we statically set the type string.
          $type = 'regulation';
          break;

        case 'event':
          // For a list text field, we need to get the field config, so we can
          // grab the human-readable label from the field based on the key.
          $list = FieldConfig::loadByName('node', 'event', 'field_event_type_list')->getSetting('allowed_values');

          if (in_array($type, $list)) {
            $type = $list[$type];
          }
          if ($type === 'general_event') {
            // Don't show the type if the type is "general_event".
            $type = NULL;
          }
          break;

        default:
          break;
      }

      if ($type) {
        return [
          '#markup' => $type,
          '#cache' => [
            'tags' => $entity->getCacheTags(),
          ],
        ];
      }
    }
    return '';
  }

}

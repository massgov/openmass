<?php

namespace Drupal\mass_content;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mayflower\Helper;

/**
 * Computes a date for a link based on a content type to field mapping.
 */
class ComputedLinkDate extends StringData {

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

      // Sets a mapping of content type to "date" fields.
      $date_fields = [
        'advisory' => 'field_date_published',
        'decision' => 'field_date_published',
        'event' => 'field_event_date',
        'executive_order' => 'field_date_published',
        'news' => 'field_date_published',
        'regulation' => 'field_date_published',
        'rules' => 'field_date_published',
      ];

      // Safe-proof against any new content types that get added, so we make
      // sure the entity bundle is in the array in the mapping.
      if (in_array($entity->bundle(), array_keys($date_fields))) {
        if (!empty($entity->{$date_fields[$entity->bundle()]}->value)) {
          $date = $entity->{$date_fields[$entity->bundle()]}->value;
          $date = new DrupalDateTime($date);

          // Get the timestamp of the date field so it can be formatted.
          $date = $date->getTimestamp();
          return [
            '#markup' => \Drupal::service('date.formatter')->format($date, 'short_date_only'),
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

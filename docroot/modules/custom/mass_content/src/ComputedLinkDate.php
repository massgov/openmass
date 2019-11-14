<?php

namespace Drupal\mass_content;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mayflower\Helper;
use Drupal\Core\Datetime\DrupalDateTime;

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
        'advisory' => 'field_advisory_date',
        'decision' => 'field_decision_date',
        'event' => 'field_event_date',
        'executive_order' => 'field_executive_order_date',
        'news' => 'field_news_date',
        'regulation' => 'field_regulation_last_updated',
        'rules' => 'field_rules_effective_date',
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

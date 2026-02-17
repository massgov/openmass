<?php

namespace Drupal\mass_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\mayflower\Helper;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

/**
 * Provides various sorting methods for lists of entities.
 */
class EntitySorter {

  /**
   * Sorts an array of entities.
   *
   * @param array $entities
   *   The entities to sort.
   * @param string $sort
   *   The type of sort to use.
   */
  public function sortEntities(array &$entities, $sort) {
    // Changes the sort order based on the option chosen in the section.
    switch ($sort) {
      case 'alpha_reverse':
        usort($entities, [$this, 'compareAlphaReverse']);
        break;

      case 'asc':
        usort($entities, [$this, 'compareDatesAsc']);
        break;

      case 'desc':
        usort($entities, [$this, 'compareDatesDesc']);
        break;

      default:
        usort($entities, [$this, 'compareAlpha']);
        break;
    }
  }

  /**
   * Sets a time format that avoid ambiguos date strings.
   *
   * To avoid potential ambiguity, it's best to use ISO 8601 (YYYY-MM-DD) dates
   * or DateTime::createFromFormat() when possible.
   *
   * @see https://www.php.net/manual/en/function.strtotime.php
   */
  protected function formatDateValue(string $date, bool $convert_to_time = TRUE) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    return $date_formatter->format($convert_to_time ? strtotime($date) : $date, 'custom', \DATE_ATOM);
  }

  /**
   * Extract a date value from an entity.
   *
   * @param object $object
   *   The entity from which to extract the date.
   *
   * @return string
   *   The date formatted for comparison.
   */
  protected function getDateValue($object) {
    $date = '';
    if ($object instanceof Node) {
      $type = $object->getType();
      switch ($type) {
        case 'advisory':
        case 'binder':
        case 'decision':
        case 'executive_order':
        case 'regulation':
        case 'rules':
          if ($date = Helper::fieldValue($object, 'field_date_published')) {
            $date = $this->formatDateValue($date);
            break;
          }

        case 'curated_list':
          $date = $this->formatDateValue($object->getCreatedTime(), FALSE);
          break;

        case 'info_details':
          if ($date = Helper::fieldValue($object, 'field_info_details_last_updated')) {
            $date = $this->formatDateValue($date);
            break;
          }

        default:
          $date = $this->formatDateValue($object->getRevisionCreationTime(), FALSE);
      }
    }
    elseif ($object instanceof Media) {
      $date = Helper::fieldValue($object, 'field_start_date');
      $date = $this->formatDateValue($date);
    }

    // If the date field is empty for any type, fallback to the last changed date.
    if (empty($date)) {
      $date = $this->formatDateValue($object->changed->value, FALSE);
    }

    return $date;
  }

  /**
   * Helper function to usort an array of objects created date.
   *
   * @param object $a
   *   First object to compare with created date field.
   * @param object $b
   *   Second object to compare with created date field.
   * @param string $direction
   *   The direction to sort in, asc for ascending or desc for descending.
   *
   * @return int
   *   Returns 0, -1 or 1.
   */
  protected function compareDates($a, $b, $direction = 'desc') {
    $a_time = $this->getDateValue($a);
    $b_time = $this->getDateValue($b);

    if ($a_time === $b_time) {
      return 0;
    }
    if ($direction == 'asc') {
      return ($a_time < $b_time) ? -1 : 1;
    }
    else {
      return ($a_time > $b_time) ? -1 : 1;
    }
  }

  /**
   * Helper function to usort an array of objects created date in asc order.
   *
   * @param object $a
   *   First object to compare with created date field.
   * @param object $b
   *   Second object to compare with created date field.
   *
   * @return int
   *   Returns 0, -1 or 1.
   */
  protected function compareDatesAsc($a, $b) {
    $a = $a->entity;
    $b = $b->entity;
    return $this->compareDates($a, $b, 'asc');
  }

  /**
   * Helper function to usort an array of objects created date in desc order.
   *
   * @param object $a
   *   First object to compare with created date field.
   * @param object $b
   *   Second object to compare with created date field.
   *
   * @return int
   *   Returns 0, -1 or 1.
   */
  protected function compareDatesDesc($a, $b) {
    $a = $a->entity;
    $b = $b->entity;
    return $this->compareDates($a, $b, 'desc');
  }

  /**
   * Helper function to usort an array of objects title in alphabetical order.
   *
   * @param object $a
   *   First object of node or media to compare with created date field.
   * @param object $b
   *   Second object of node or media to compare with created date field.
   *
   * @return int
   *   Returns 0, -1 or 1.
   */
  protected function compareAlpha($a, $b) {
    $a_title = strtolower($this->getComparisonTitle($a->entity));
    $b_title = strtolower($this->getComparisonTitle($b->entity));

    return strnatcmp($a_title, $b_title);
  }

  /**
   * Determine the title of an entity to use as the basis for comparison.
   */
  private function getComparisonTitle(EntityInterface $entity) {
    // Derive the title from `field_title` on media entities.
    if ($entity instanceof Media) {
      return Helper::fieldValue($entity, 'field_title');
    }
    // For specific content types, we derive the title special ways.
    if ($entity instanceof Node) {
      switch ($entity->bundle()) {
        case 'person':
          return Helper::fieldValue($entity, 'field_person_last_name') . ' ' . Helper::fieldValue($entity, 'field_person_first_name');

        case 'contact_information':
          return Helper::fieldValue($entity, 'field_display_title');
      }
    }

    // Fallback to entity label if we can't figure out a more specific one.
    return $entity->label();
  }

  /**
   * Helper function to usort an array of objects title in reverse-alpha order.
   *
   * This is the inverse of compareAlpha().
   *
   * @param object $a
   *   First object of node or media to compare with created date field.
   * @param object $b
   *   Second object of node or media to compare with created date field.
   *
   * @return int
   *   Returns 0, -1 or 1.
   */
  protected function compareAlphaReverse($a, $b) {
    // Let the other function do the legwork.
    $answer = $this->compareAlpha($a, $b);

    if ($answer === 0) {
      return 0;
    }

    // Reverse the order.
    if ($answer === -1) {
      return 1;
    }
    return -1;
  }

}

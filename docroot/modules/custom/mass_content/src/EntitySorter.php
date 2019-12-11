<?php

namespace Drupal\mass_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\mayflower\Helper;
use Drupal\media\Entity\Media;

/**
 * Provides various sorting methods for lists of entities.
 */
class EntitySorter {

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

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
          $date = Helper::fieldValue($object, 'field_advisory_date');
          break;

        case 'binder':
          $date = Helper::fieldValue($object, 'field_binder_date_published');
          break;

        case 'decision':
          $date = Helper::fieldValue($object, 'field_decision_date');
          break;

        case 'executive_order':
          $date = Helper::fieldValue($object, 'field_executive_order_date');
          break;

        case 'info_details':
          $date = Helper::fieldValue($object, 'field_info_details_last_updated');
          break;

        case 'regulation':
          $date = Helper::fieldValue($object, 'field_regulation_last_updated');
          break;

        case 'rules':
          $date = Helper::fieldValue($object, 'field_rules_effective_date');
          break;

        default:
          $date = date('Y-d-m', $object->changed->value);
      }
    }
    elseif ($object instanceof Media) {
      $date = Helper::fieldValue($object, 'field_start_date');
    }

    // If the date field is empty for any type, fallback to the last changed date.
    if (empty($date)) {
      $date = date('Y-d-m', $object->changed->value);
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
    $a_date = $this->getDateValue($a);
    $b_date = $this->getDateValue($b);
    if (empty($a_date) || empty($b_date)) {
      return 0;
    }
    $a_time = strtotime($a_date);
    $b_time = strtotime($b_date);
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

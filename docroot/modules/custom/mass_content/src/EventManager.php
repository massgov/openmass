<?php

namespace Drupal\mass_content;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Lightweight event manager.
 *
 * Queries for events in a performant way.
 */
class EventManager {

  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, StateInterface $state) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Return the base query all queries build on.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  private function getBaseQuery(NodeInterface $parent) {
    $query = $this->getStorage()->getQuery();
    $query->condition('type', 'event');
    $query->condition('status', 1);

    switch ($parent->bundle()) {
      case 'event':
        // Events can only be related to other events via the
        // field_event_ref_event_2 field.
        $ids = [];
        foreach ($parent->field_event_ref_event_2 as $item) {
          $ids[] = $item->target_id;
        }
        if ($ids) {
          $query->condition('nid', $ids, 'IN');
        }
        else {
          // Arbitrary condition to avoid returning any results.
          $query->condition('nid', -111);
        }

        break;

      default:
        // Other content types can have events referenced through
        // the event's field_event_ref_parents field.
        $query->condition('field_event_ref_parents', $parent->id());
        break;
    }

    return $query;
  }

  /**
   * Build a query against events that have occurred.
   */
  private function getPastQuery(NodeInterface $parent) {
    $query = $this->getBaseQuery($parent);
    $now = new DrupalDateTime('now');
    $query->condition('field_event_date.end_value', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=');
    return $query;
  }

  /**
   * Build a query against events that have yet to occur.
   */
  private function getUpcomingQuery(NodeInterface $parent) {
    $query = $this->getBaseQuery($parent);
    $upcomingDateEndDate = new DrupalDateTime('today');
    $query->condition('field_event_date.end_value', $upcomingDateEndDate->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>');
    return $query;
  }

  /**
   * Get the node storage instance.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The node storage.
   */
  private function getStorage() {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * Retrieve a list of the next $limit upcoming events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   * @param int $limit
   *   The number of items to return (-1 for unlimited)
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The events.
   */
  public function getUpcoming(NodeInterface $parent, int $limit = 10) {
    $query = $this->getUpcomingQuery($parent);

    $query
      ->sort('field_event_date.value', 'ASC')
      ->sort('title', 'ASC');
    if ($limit !== -1) {
      $query->range(0, $limit);
    }

    return $this->getStorage()->loadMultiple($query->accessCheck(FALSE)->execute());
  }

  /**
   * Retrieve an array of recently past events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   * @param int $limit
   *   The number of items to return (-1 for unlimited).
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The events.
   */
  public function getPast(NodeInterface $parent, int $limit = 10) {
    $query = $this->getPastQuery($parent);
    $query
      ->sort('field_event_date.end_value', 'DESC')
      ->sort('title', 'ASC');
    if ($limit !== -1) {
      $query->range(0, $limit);
    }

    return $this->getStorage()->loadMultiple($query->accessCheck(FALSE)->execute());
  }

  /**
   * Return a count of past events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   *
   * @return array|int
   *   The number of events that are in the past.
   */
  public function getPastCount(NodeInterface $parent) {
    $query = $this->getPastQuery($parent);
    return $query->accessCheck(FALSE)->count()->execute();
  }

  /**
   * Return a count of upcoming events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   *
   * @return array|int
   *   The number of events that are upcoming.
   */
  public function getUpcomingCount(NodeInterface $parent) {
    $query = $this->getUpcomingQuery($parent);
    return $query->accessCheck(FALSE)->count()->execute();
  }

  /**
   * Return the max age for events related to parent.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   * @param int $max_age_limit
   *   The maximum age limit for event listings defaults to one week.
   *
   * @return int
   *   The max age cache setting for an event listing.
   */
  public function getMaxAge(NodeInterface $parent, $max_age_limit = 604800) {
    $max_age = Cache::PERMANENT;
    if ($this->hasUpcoming($parent)) {
      $next = $this->getUpcoming($parent, 1);
      $end = reset($next)->field_event_date->end_date;
      if (!empty($end)) {
        $now = new DrupalDateTime();
        $max_age = $end->format('U') - $now->format('U');
      }
    }
    return ($max_age < $max_age_limit) ? $max_age : $max_age_limit;
  }

  /**
   * Check whether there are upcoming events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   *
   * @return bool
   *   Indication of whether there is an upcoming event.
   */
  public function hasUpcoming(NodeInterface $parent) {
    return (bool) $this->getUpcomingCount($parent);
  }

  /**
   * Check whether there are past events.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   *
   * @return bool
   *   Indication of whether there is a past event.
   */
  public function hasPast(NodeInterface $parent) {
    return (bool) $this->getPastCount($parent);
  }

}

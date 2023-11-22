<?php

declare(strict_types=1);

namespace Drupal\mass_content;

use Drupal\mayflower\Helper;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Render events for org_events paragraph.
 */
class EventsRendererOrgPages {

  private EventManager $eventManager;

  /**
   * Stores if there are past events.
   */
  private bool $hasPastEvents;

  /**
   * The parent node where the paragraph is referenced.
   */
  private ?NodeInterface $parentNode;

  private ParagraphInterface $paragraph;

  /**
   * Creates an events renderer object.
   */
  public function __construct(EventManager $event_manager, ParagraphInterface $paragraph) {
    $this->eventManager = $event_manager;
    $this->paragraph = $paragraph;
    $this->parentNode = Helper::getParentNode($paragraph);
    $this->hasPastEvents = $this->parentNode && $this->eventManager->hasPast($this->parentNode);
  }

  /**
   * Returns a render array with the upcoming or past events.
   */
  public function render() {
    // Gather events associated with this node.
    $eventManager = \Drupal::service('mass_content.event_manager');
    $upcoming = $eventManager->hasUpcoming($this->parentNode);
    $render = $upcoming ? $this->nextEvents() : $this->pastEventsLink();
    // To update if any event changes.
    $render['#cache']['tags'][] = 'node_list:event';
    // Expire at the end of every day, so that upcoming is not stale.
    $today = new \DateTime('now');
    $tomorrow = new \DateTime('tomorrow');
    $difference = strtotime('tomorrow') - strtotime('now');
    $render['#cache']['max-age'] = $difference;
    return $render;
  }

  /**
   * Returns a render array with upcoming events.
   */
  private function nextEvents() {
    // Set the limit on how many events should be displayed on the page. Default
    // to 2 if no limit is set.
    $limit = $this->paragraph->hasField('field_event_quantity') &&
      $this->paragraph->field_event_quantity->isEmpty() ?
        2 : (int) $this->paragraph->field_event_quantity->value;

    $events = Helper::prepareEvents($this->eventManager->getUpcoming($this->parentNode, $limit + 1));
    $moreButton = '';

    // Display the see all events link if there are either more than the limit
    // set for upcoming events in Drupal or <=2 upcoming with 1+ past events.
    if (count($events) > $limit || $this->hasPastEvents) {
      $moreButton = Helper::prepareMoreLink($this->parentNode, ['text' => t('See all events')]);
    }

    $eventsData = [
      'pageContent' => [
        [
          'path' => '@organisms/by-author/event-listing.twig',
          'data' => [
            'eventListing' => [
              'grid' => TRUE,
              'events' => array_splice($events, 0, $limit),
              'more' => $moreButton,
            ],
          ],
        ],
      ],
    ];
    $max_age = $this->eventManager->getMaxAge($this->parentNode);
    $cache['max-age'] = $max_age;

    return ['events' => $eventsData, '#cache' => $cache];
  }

  /**
   * Returns a render array with previous events, if any.
   */
  private function pastEventsLink() {

    if (!$this->hasPastEvents) {
      return [];
    }

    $eventsData = [
      'pageContent' => [
        [
          'path' => '@organisms/by-author/event-listing.twig',
          'data' => [
            'eventListing' => [
              'grid' => FALSE,
              'emptyText' => t('No upcoming events scheduled'),
              'pastMore' => [
                "text" => t('See past events'),
                "href" => \Drupal::service('path_alias.manager')
                  ->getAliasByPath('/node/' . $this->parentNode->id()) . '/events/past',
                "chevron" => TRUE,
              ],
            ],
          ],
        ],
      ],
    ];
    return ['events' => $eventsData];
  }

}

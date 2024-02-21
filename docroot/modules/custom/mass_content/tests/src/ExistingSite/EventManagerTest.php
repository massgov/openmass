<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests EventManager.
 */
class EventManagerTest extends MassExistingSiteBase {

  private $org;
  private $event1;
  private $event2;
  private $event3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->org = $this->createNode([
      'type' => 'org_page',
    ]);
    $upcoming = new DrupalDateTime('now +1 day');
    $upcoming = $upcoming->setTimeZone(new \DateTimeZone('UTC'));
    $past = new DrupalDateTime('now -1 day');
    $this->event1 = $this->createNode([
      'type' => 'event',
      'field_event_ref_parents' => [$this->org],
      'field_event_date' => [
        'value' => $upcoming->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $upcoming->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
      'moderation_state' => 'published',
    ]);
    $this->event2 = $this->createNode([
      'type' => 'event',
      'field_event_ref_parents' => [$this->org],
      'field_event_ref_event_2' => [$this->event1],
      'field_event_date' => [
        'value' => $past->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $past->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
      'moderation_state' => 'published',
    ]);
    $upcomingSoon = new DrupalDateTime('now +2 hours');
    $upcomingSoon = $upcomingSoon->setTimeZone(new \DateTimeZone('UTC'));
    $this->event3 = $this->createNode([
      'type' => 'event',
      'field_event_ref_parents' => [$this->org],
      'field_event_date' => [
        'value' => $past->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $upcomingSoon->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
      'moderation_state' => 'published',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->org = NULL;
    $this->event1 = NULL;
    $this->event2 = NULL;
    $this->event3 = NULL;
  }

  /**
   * Checks EventManager functionality as it relates to orgs.
   */
  public function testOrgEvents() {
    /** @var \Drupal\mass_content\EventManager $em */
    $em = \Drupal::service('mass_content.event_manager');
    $upcoming = $em->getUpcoming($this->org);
    $this->assertEventInArray($this->event1, $upcoming);
    $this->assertEventNotInArray($this->event2, $upcoming);
    $this->assertEquals(2, $em->getUpcomingCount($this->org));
    $this->assertEquals($this->event3->id(), reset($upcoming)->id(), 'Upcoming events are sorted by start date.');
    $this->assertCount(1, $em->getUpcoming($this->org, 1), 'Upcoming events can be limited');

    $past = $em->getPast($this->org);
    $this->assertEventInArray($this->event2, $past);
    $this->assertEventNotInArray($this->event1, $past);
    $this->assertEquals(1, $em->getPastCount($this->org));
  }

  /**
   * Test EventManager functionality as it relates to other events.
   */
  public function testUpcomingRelatedEvents() {
    /** @var \Drupal\mass_content\EventManager $em */
    $em = \Drupal::service('mass_content.event_manager');
    $this->assertEquals(0, $em->getUpcomingCount($this->event1));
    $this->assertEquals(1, $em->getUpcomingCount($this->event2));
    $this->assertEquals(0, $em->getPastCount($this->event1));
    $this->assertEquals(0, $em->getPastCount($this->event2));
  }

  /**
   * Helper to check for the presence of an event in an array.
   */
  private function assertEventInArray(NodeInterface $expected, array $events) {
    $matching = array_filter($events, function ($event) use ($expected) {
      return $event->id() === $expected->id();
    });
    $this->assertCount(1, $matching, 'Expected event was found in array.');
  }

  /**
   * Helper to check for the absence of an event in an array.
   */
  private function assertEventNotInArray(NodeInterface $expected, array $events) {
    $matching = array_filter($events, function ($event) use ($expected) {
      return $event->id() === $expected->id();
    });
    $this->assertCount(0, $matching, 'Event was not found in array.');
  }

}

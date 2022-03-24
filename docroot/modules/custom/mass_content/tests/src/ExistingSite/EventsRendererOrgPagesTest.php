<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests EventsRendererOrgPagesTest.
 */
class EventsRendererOrgPagesTest extends ExistingSiteBase {

  /**
   * Org page.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $org;


  /**
   * Event.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $event1;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $event = Paragraph::create([
      'type' => 'org_events',
      'field_event_quantity' => 1,
    ]);

    $sections_wrapper = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$event],
    ]);

    $this->org = $this->createNode([
      'type' => 'org_page',
      'moderation_state' => MassModeration::PUBLISHED,
      'field_organization_sections' => [
        $sections_wrapper,
      ],
    ]);
  }

  /**
   * Creates an event only valid for the next minute.
   *
   * @see \Drupal\mass_content\EventManager::getUpcomingQuery
   */
  private function createUpcomingEvent() {
    $upcoming = new DrupalDateTime('now +1 seconds');
    $upcoming = $upcoming->setTimeZone(new \DateTimeZone('UTC'));
    $this->upcomingMinute = $upcoming->format('i');

    $this->event1 = $this->createNode([
      'title' => $this->randomMachineName(20),
      'type' => 'event',
      'field_event_ref_parents' => [$this->org],
      'field_event_date' => [
        'value' => $upcoming->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $upcoming->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Gets the response headers for the page.
   */
  private function getResponseHeaders() {
    $session = $this->getSession();
    $headers = $session->getResponseHeaders();
    return $headers;
  }

  /**
   * Checks current page contains the node_list:event cache tag.
   */
  private function checkNodeListEventTag() {
    $cache_tags = $this->getResponseHeaders()['X-Drupal-Cache-Tags'][0];
    $this->assertStringContainsString('node_list:event', $cache_tags, 'Event list not changing when an event changes.');
  }

  /**
   * Checks org page shows the event (or not).
   */
  private function checkEventOrgPage($event_appears) {
    $this->assertEquals($this->getCurrentPage()->hasContent($this->event1->label()), $event_appears);
  }

  /**
   * Checks events dissapears from Org page.
   */
  public function testOrgEvents() {

    // Visit the org page created on setup.
    $this->drupalGet($this->org->toUrl());

    $this->checkNodeListEventTag();
    $this->createUpcomingEvent();

    $this->drupalGet($this->org->toUrl());
    $this->checkEventOrgPage(TRUE);

    // Wait for the next minute.
    $current_minute = date('i');
    while ($current_minute == date('i')) {
      sleep(1);
    };
    \Drupal::service('cron')->run();

    // Check org page again, event should be gone.
    $this->drupalGet($this->org->toUrl());
    $this->checkEventOrgPage(FALSE);
    $this->checkNodeListEventTag();
  }

}

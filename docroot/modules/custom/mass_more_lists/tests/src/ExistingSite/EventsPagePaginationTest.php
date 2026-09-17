<?php

namespace Drupal\Tests\mass_more_lists\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests server-side pagination of org event listing pages.
 */
class EventsPagePaginationTest extends MassExistingSiteBase {

  /**
   * Verifies past events listings only render one page of results.
   */
  public function testPastEventsArePaginated() {
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Events pagination org',
      'moderation_state' => 'published',
    ]);
    $tz = new \DateTimeZone(date_default_timezone_get());

    $event_titles = [];
    for ($i = 1; $i <= 11; $i++) {
      $date = (new DrupalDateTime("now -{$i} day"))->setTimeZone($tz);
      $title = sprintf('Past event %02d', $i);
      $event_titles[] = $title;
      $this->createNode([
        'type' => 'event',
        'title' => $title,
        'field_event_ref_parents' => [$org],
        'field_event_date' => [
          'value' => $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          'end_value' => $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        ],
        'moderation_state' => 'published',
      ]);
    }

    $this->drupalGet('/node/' . $org->id() . '/events/past');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing 1–10 of 11 results');
    $this->assertSession()->elementsCount('css', '.ma__event-listing__item', 10);
    $this->assertSession()->pageTextContains($event_titles[0]);
    $this->assertSession()->pageTextNotContains($event_titles[10]);

    $this->drupalGet('/node/' . $org->id() . '/events/past', ['query' => ['page' => 1]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing 11–11 of 11 results');
    $this->assertSession()->elementsCount('css', '.ma__event-listing__item', 1);
    $this->assertSession()->pageTextContains($event_titles[10]);

    $this->drupalGet('/node/' . $org->id() . '/events/past', ['query' => ['_page' => 2]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing 11–11 of 11 results');
    $this->assertSession()->pageTextContains($event_titles[10]);
    $this->assertStringContainsString('page=1', $this->getSession()->getCurrentUrl());
  }

}

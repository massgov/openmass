<?php

namespace Drupal\Tests\mass_more_lists\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\mass_more_lists\Controller\EventsController;
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
    $limit = EventsController::PAST_EVENTS_PER_PAGE;
    $total = $limit + 1;

    $event_titles = [];
    for ($i = 1; $i <= $total; $i++) {
      $date = (new DrupalDateTime("now -{$i} day"))->setTimeZone($tz);
      $title = sprintf('Past event %03d', $i);
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

    $path = '/node/' . $org->id() . '/events/past';

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing 1–' . $limit . ' of ' . $total . ' results');
    $this->assertSession()->elementsCount('css', '.ma__event-listing__item', $limit);
    $this->assertSession()->pageTextContains($event_titles[0]);
    $this->assertSession()->pageTextNotContains($event_titles[$limit]);

    $this->drupalGet($path, ['query' => ['page' => 1]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing ' . $total . '–' . $total . ' of ' . $total . ' results');
    $this->assertSession()->elementsCount('css', '.ma__event-listing__item', 1);
    $this->assertSession()->pageTextContains($event_titles[$limit]);

    $this->drupalGet($path, ['query' => ['page' => 2]]);
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalGet($path, ['query' => ['_page' => 2]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing ' . $total . '–' . $total . ' of ' . $total . ' results');
    $this->assertSession()->pageTextContains($event_titles[$limit]);
    $this->assertStringContainsString('page=1', $this->getSession()->getCurrentUrl());
  }

}

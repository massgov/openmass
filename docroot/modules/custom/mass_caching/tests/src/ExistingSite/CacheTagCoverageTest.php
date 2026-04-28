<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\mass_utility\DebugCachability;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies cache tags bubble from referenced content to rendered pages.
 */
class CacheTagCoverageTest extends MassExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    (new DebugCachability())->requestDebugCachabilityHeaders($this->getSession());
  }

  /**
   * Org location maps should bubble cache tags for referenced locations.
   */
  public function testOrgLocationsBubbleReferencedLocationTags(): void {
    $location = $this->createNode([
      'type' => 'location',
      'title' => 'Cache Tag Test Location',
      'moderation_state' => 'published',
    ]);

    $org_locations = Paragraph::create([
      'type' => 'org_locations',
      'field_org_ref_locations' => [$location],
    ]);
    $org_locations->save();

    $section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$org_locations],
    ]);
    $section->save();

    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Cache Tag Test Org',
      'field_organization_sections' => [$section],
      'moderation_state' => 'published',
    ]);

    $this->drupalGet($org_page->toUrl()->toString());
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'node:' . $location->id());
  }

  /**
   * Event headers should bubble cache tags for unique-address paragraphs.
   */
  public function testEventUniqueAddressBubblesParagraphTags(): void {
    $parent = $this->createNode([
      'type' => 'org_page',
      'title' => 'Cache Tag Event Parent',
      'moderation_state' => 'published',
    ]);

    $address = Paragraph::create([
      'type' => 'address',
      'field_label' => 'Unique Event Address',
      'field_address_address' => [
        'address_line1' => '1 Test Plaza',
        'locality' => 'Boston',
        'administrative_area' => 'MA',
        'postal_code' => '02108',
        'country_code' => 'US',
      ],
    ]);
    $address->save();

    $event = $this->createNode([
      'type' => 'event',
      'title' => 'Cache Tag Test Event',
      'field_event_address_type' => 'unique',
      'field_event_ref_unique_address' => [$address],
      'field_event_date' => [
        'value' => '2030-12-31T05:00:00',
        'end_value' => '2031-01-01T05:00:00',
      ],
      'field_event_time' => '6AM - 5PM',
      'field_event_ref_parents' => [$parent],
      'field_organizations' => [$parent],
      'moderation_state' => 'published',
    ]);

    $this->drupalGet($event->toUrl()->toString());
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'paragraph:' . $address->id());
  }

  /**
   * Related link lists should bubble tags for linked internal nodes.
   */
  public function testRelatedLinkListBubblesLinkedNodeTags(): void {
    $related = $this->createNode([
      'type' => 'org_page',
      'title' => 'Cache Tag Related Org',
      'moderation_state' => 'published',
    ]);

    $info_details = $this->createNode([
      'type' => 'info_details',
      'title' => 'Cache Tag Info Details',
      'field_info_details_related' => [
        [
          'uri' => 'entity:node/' . $related->id(),
          'title' => $related->label(),
        ],
      ],
      'moderation_state' => 'published',
    ]);

    $this->drupalGet($info_details->toUrl()->toString());
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'node:' . $related->id());
  }

}

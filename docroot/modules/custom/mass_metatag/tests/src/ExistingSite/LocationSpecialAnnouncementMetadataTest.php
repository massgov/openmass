<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;

/**
 * Location Special Announcement metadata tests.
 */
class LocationSpecialAnnouncementMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $image->save();
    $this->markEntityForCleanup($image);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => 'published',
    ]);
    $node = $this->createNode([
      'type' => 'location',
      'title' => 'Test Location',
      'field_ref_contact_info_1' => $this->createContact(),
      'field_overview' => 'Test Overview',
      'field_location_more_information' => 'Test More Info',
      'field_state_organization_tax' => [$org_term],
      'field_services' => $this->createTextField('Test Services'),
      'field_bg_wide' => $image,
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
      'field_location_metatags' => [
        'schema_special_announcement_type' => 'SpecialAnnouncement',
        'schema_special_announcement_name' => 'test',
        'schema_special_announcement_text' => 'test',
        'schema_special_announcement_category' => 'test',
        'schema_special_announcement_date_posted' => '0000-00-00',
        'schema_special_announcement_expires' => '0000-00-00',
        'schema_special_announcement_url' => 'http://schema.org',
        'schema_special_announcement_location' => [
          '@type' => 'CivicStructure',
          'name' => 'test',
          'url' => 'http://schema.org',
          'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '1600 Amphitheatre Pkwy',
            'addressLocality' => 'Mountain View',
            'addressRegion' => 'CA',
            'postalCode' => '94043',
            'addressCountry' => 'USA',
          ],
        ],
        'schema_special_announcement_disease_spread_statistics' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'schema_special_announcement_disease_prevention_info' => [
          '@type' => 'WebPage',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'schema_special_announcement_getting_tested_info' => [
          '@type' => 'WebPageElement',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'schema_special_announcement_government_benefits_info' => [
          '@type' => 'WebContent',
          '@id' => 'http://example.com',
          'name' => 'test',
          'url' => 'http://example.com',
          'sameAs' => 'test',
          'datePublished' => 'test',
        ],
        'schema_special_announcement_news_updates_and_guidelines' => [
          '@type' => 'WebPage',
          '@id' => 'test',
          'name' => 'test',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
        'schema_special_announcement_public_transport_closures_info' => [
          '@type' => 'WebPageElement',
          '@id' => 'http://schema.org',
          'name' => 'TEST',
          'url' => 'http://schema.org',
          'sameAs' => 'TEST',
          'datePublished' => '0000-00-00',
        ],
        'schema_special_announcement_quarantine_guidelines' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'http://url.com',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
        'schema_special_announcement_school_closures_info' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'schema_special_announcement_spatial_coverage' => [
          '@type' => 'State',
          'name' => 'test',
          'url' => 'http://schema.org',
          'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '1600 Amphitheatre Pkwy',
            'addressLocality' => 'Mountain View',
            'addressRegion' => 'CA',
            'postalCode' => '94043',
            'addressCountry' => 'USA',
          ],
          'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => '37.42242',
            'longitude' => '-122.08585',
          ],
          'country' => [
            '@type' => 'Country',
            'name' => 'USA',
          ],
        ],
        'schema_special_announcement_travel_bans' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'http://url.com',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
      ],
    ]);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'Test Overview',
      'twitter:card' => 'summary_large_image',
      'twitter:description' => 'Test Overview',
      'og:description' => 'Test Overview',
      'geo.placename' => $entity->label(),
      'geo.region' => 'US-MA',
      'mg_phone_number' => '123-456-7890',
      'mg_address' => "123 Test Way\nBoston, MA 12345\nUnited States",
      'mg_stakeholder_org' => 'TestOrgTerm',
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();

    return array_merge(parent::getExpectedMetadata($entity), [
      $url . '#specialannouncement' => [
        '@context' => 'https://schema.org',
        '@type' => 'SpecialAnnouncement',
        '@id' => $url . '#specialannouncement',
        'name' => 'test',
        'text' => 'test',
        'category' => 'test',
        'datePosted' => '0000-00-00',
        'expires' => '0000-00-00',
        'url' => 'http://schema.org',
        'announcementLocation' => [
          '@type' => 'CivicStructure',
          'name' => 'test',
          'url' => 'http://schema.org',
          'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '1600 Amphitheatre Pkwy',
            'addressLocality' => 'Mountain View',
            'addressRegion' => 'CA',
            'postalCode' => '94043',
            'addressCountry' => 'USA',
          ],
        ],
        'diseasePreventionInfo' => [
          '@type' => 'WebPage',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'diseaseSpreadStatistics' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'governmentBenefitsInfo' => [
          '@type' => 'WebContent',
          '@id' => 'http://example.com',
          'name' => 'test',
          'url' => 'http://example.com',
          'sameAs' => 'test',
          'datePublished' => 'test',
        ],
        'gettingTestedInfo' => [
          '@type' => 'WebPageElement',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'newsUpdatesAndGuidelines' => [
          '@type' => 'WebPage',
          '@id' => 'test',
          'name' => 'test',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
        'publicTransportClosuresInfo' => [
          '@type' => 'WebPageElement',
          '@id' => 'http://schema.org',
          'name' => 'TEST',
          'url' => 'http://schema.org',
          'sameAs' => 'TEST',
          'datePublished' => '0000-00-00',
        ],
        'quarantineGuidelines' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'http://url.com',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
        'schoolClosuresInfo' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'test',
          'url' => 'http://schema.org',
          'sameAs' => 'http://schema.org',
          'datePublished' => '2020-05-15',
        ],
        'spatialCoverage' => [
          '@type' => 'State',
          'name' => 'test',
          'url' => 'http://schema.org',
          'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '1600 Amphitheatre Pkwy',
            'addressLocality' => 'Mountain View',
            'addressRegion' => 'CA',
            'postalCode' => '94043',
            'addressCountry' => 'USA',
          ],
          'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => '37.42242',
            'longitude' => '-122.08585',
          ],
          'country' => [
            '@type' => 'Country',
            'name' => 'USA',
          ],
        ],
        'travelBans' => [
          '@type' => 'WebSite',
          '@id' => 'http://schema.org',
          'name' => 'http://url.com',
          'url' => 'test',
          'sameAs' => 'test',
          'datePublished' => '0000-00-00',
        ],
      ],
    ]);
  }

}

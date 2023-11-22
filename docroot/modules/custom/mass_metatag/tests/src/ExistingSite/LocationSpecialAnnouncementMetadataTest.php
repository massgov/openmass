<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

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
      'field_location_metatags' => serialize(array_merge($this->getWebContentTestData(TRUE), [
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
      ])),
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
      'mg_address' => "123 Test Way Boston, MA 12345 United States",
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();

    return array_merge(parent::getExpectedMetadata($entity), [
      $url . '#specialannouncement' => array_merge($this->getWebContentTestData(), [
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
      ]),
    ]);
  }

  /**
   * Function to populate test WebContent metadata.
   */
  private function getWebContentTestData($snake_case = FALSE) {
    $webcontent_metadata = [];

    $tags = [
      'diseasePreventionInfo' => 'WebContent',
      'diseaseSpreadStatistics' => 'WebPage',
      'governmentBenefitsInfo' => 'WebPageElement',
      'gettingTestedInfo' => 'WebSite',
      'newsUpdatesAndGuidelines' => 'WebContent',
      'publicTransportClosuresInfo' => 'WebPage',
      'quarantineGuidelines' => 'WebPageElement',
      'schoolClosuresInfo' => 'WebSite',
      'travelBans' => 'WebContent',
    ];

    foreach ($tags as $tag => $webcontent_type) {
      $tag_key = $tag;
      if ($snake_case === TRUE) {
        $name_converter = new CamelCaseToSnakeCaseNameConverter();
        $tag_key = 'schema_special_announcement_' . $name_converter->normalize($tag);
      }

      $webcontent_metadata[$tag_key] = [
        '@type' => $webcontent_type,
        '@id' => 'http://schema.org/' . $tag,
        'name' => 'http://schema.org/' . $tag,
        'url' => 'http://schema.org/' . $tag,
        'sameAs' => 'http://schema.org/' . $tag,
        'datePublished' => '0000-00-00',
      ];
    }

    return $webcontent_metadata;
  }

}

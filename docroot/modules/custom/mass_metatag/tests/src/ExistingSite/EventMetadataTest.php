<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Event metadata tests.
 */
class EventMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $this->markEntityForCleanup($image);

    $node = $this->createNode([
      'type' => 'event',
      'title' => 'Test Event',
      'field_event_link_sign_up' => [
        'title' => 'Take Action!',
        'uri' => 'https://google.com',
      ],
      'field_event_date' => [
        'value' => '2012-12-31T05:00:00',
        'end_value' => '2013-01-01T05:00:00',
      ],
      'field_event_time' => '6AM - 5PM',
      'field_state_organization_tax' => $org_term,
      'field_event_ref_contact' => $this->createContact(),
      'field_event_ref_parents' => [$org_node],
      'field_event_capacity' => 'a lot of people',
      'field_event_links' => [
        'uri' => 'http://example.com',
      ],
      'field_event_image' => $image,
      'field_event_lede' => $this->createTextField('Test Lede'),
      'field_event_fees' => $this->createTextField('$1 per person'),
      'field_event_description' => $this->createTextField('Test Description'),
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'Test Lede',
      'twitter:description' => 'Test Lede',
      'og:description' => 'Test Lede',
      'mg_date' => '20121231',
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return array_merge(parent::getExpectedMetadata($entity), [
      $url . '#event' => [
        '@id' => $url . '#event',
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $entity->label(),
        'description' => 'Test Description',
        'image' => [
          [
            '@type' => 'ImageObject',
            'url' => [\Drupal::service('file_url_generator')->generateAbsoluteString('public://test.jpg')],
          ],
        ],
        'maximumAttendeeCapacity' => 'a lot of people',
        'disambiguatingDescription' => 'Test Lede',
        'offers' => [
          '@type' => 'Offer',
          'price' => '1 per person',
          'priceCurrency' => 'USD',
        ],
        'startDate' => "2012-12-31T00:00:00-0500\n - 2013-01-01T00:00:00-0500",
        'potentialAction' => [
          [
            'name' => 'Take Action!',
            'url' => 'https://google.com',
          ],
        ],
        'location' => [
          [
            '@type' => 'Place',
            'address' => [
              '@type' => 'PostalAddress',
              'addressCountry' => 'US',
              'addressLocality' => 'Boston',
              'addressRegion' => 'MA',
              'postalCode' => '12345',
              'streetAddress' => '123 Test Way',
            ],
          ],
        ],
        'sameAs' => ['http://example.com'],
      ],
    ]);
  }

}

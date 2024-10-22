<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Location metadata tests.
 */
class LocationMetadataTest extends MetadataTestCase {

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
      $url . '#location' => [
        '@context' => 'https://schema.org',
        '@type' => 'Place',
        '@id' => $url . '#location',
        'name' => 'Test Location',
        'description' => 'Test Overview',
        'disambiguatingDescription' => 'Test More Info',
        'amenityFeature' => 'Test Services',
        'address' => [
          [
            '@type' => 'PostalAddress',
            'addressCountry' => 'US',
            'addressLocality' => 'Boston',
            'addressRegion' => 'MA',
            'streetAddress' => '123 Test Way',
            'postalCode' => '12345',
          ],
        ],
        'photo' => [
          [
            \Drupal::service('file_url_generator')->generateAbsoluteString('public://test.jpg'),
          ],
        ],
      ],
    ]);
  }

}

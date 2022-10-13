<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service Details metadata tests.
 */
class ServiceDetailsDataTabResourceMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $data_type_term = $this->createTerm(Vocabulary::load('tx_details_data_type'), [
      'name' => 'TestDataType',
      'field_details_datatype_metatag' => 'test-resource-tag',
    ]);
    $resource_term = $this->createTerm(Vocabulary::load('tx_data_resource_type'), [
      'name' => 'TestDataResource',
      'field_dataresource_metatag' => 'test-resource-tag',
    ]);
    $section = Paragraph::create([
      'type' => 'section',
      'field_section_links' => [
        'url' => 'http://example.com/1',
        'title' => 'Section Link',
      ],
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $node = $this->createNode([
      'type' => 'service_details',
      'title' => 'Test Service Details',
      'field_service_detail_links_5' => [
        'url' => 'http://google.com',
        'title' => 'Awesome Action',
      ],
      'field_service_detail_overview' => [
        'value' => 'Test Overview',
        'format' => 'basic_html',
      ],
      'field_service_detail_lede' => [
        'value' => 'Test Lede',
        'format' => 'basic_html',
      ],
      'field_service_detail_sections' => [$section],
      'field_state_organization_tax' => [$org_term],
      'field_data_flag' => ['data'],
      'field_organizations' => [$org_node],
      'field_details_data_type' => [$data_type_term],
      'field_data_resource_type' => [$resource_term],
      'moderation_state' => 'published',
    ]);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'og:description' => 'Test Lede',
      'twitter:description' => 'Test Lede',
      'mg_organization' => 'testorgpage',
      'category' => 'data',
      'mg_type' => 'test-resource-tag',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $uri = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();

    return array_merge(parent::getExpectedMetadata($entity), [
      $uri . '#service_detail' => [
        '@context' => 'https://schema.org',
        '@type' => 'GovernmentService',
        '@id' => $uri . '#service_detail',
        'name' => $entity->label(),
        'potentialAction' => [],
        'description' => 'Test Overview',
        'disambiguatingDescription' => 'Test Lede',
      ],
    ]);
  }

}

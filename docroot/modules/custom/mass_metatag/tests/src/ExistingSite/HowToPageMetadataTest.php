<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * How-To Page metadata tests.
 */
class HowToPageMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $method = Paragraph::create([
      'type' => 'method',
      'field_method_type' => 'online',
      'field_method_details' => $this->createTextField('Test Method Details'),
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $node = $this->createNode([
      'type' => 'how_to_page',
      'title' => 'Test How To',
      'field_how_to_lede' => $this->createTextField('Test Lede'),
      'field_state_organization_tax' => [$org_term],
      'field_how_to_link_1' => [
        'uri' => 'https://www.google.com',
        'title' => 'Take Action!',
      ],
      'field_how_to_methods_5' => [$method],
      'field_how_to_contacts_3' => $this->createContact(),
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
      'category' => 'services',
      'twitter:description' => 'Test Lede',
      'mg_organization' => 'testorgpage',
      'mg_key_actions' => '[{"name":"Take Action!","url":"https:\/\/www.google.com"}]',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $uri = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return array_merge(parent::getExpectedMetadata($entity), [
      $uri . '#how-to' => [
        '@context' => 'https://schema.org',
        '@type' => 'ApplyAction',
        '@id' => $uri . '#how-to',
        'name' => 'Test How To',
        'location' => [
          [
            '@type' => 'PostalAddress',
            'addressCountry' => 'US',
            'addressLocality' => 'Boston',
            'addressRegion' => 'MA',
            'streetAddress' => '123 Test Way',
            'postalCode' => '12345',
          ],
        ],
        'disambiguatingDescription' => 'Test Lede',
        'instrument' => "Online\n Test Method Details",
        'potentialAction' => [],
        'target' => [
          [
            '@type' => 'EntryPoint',
            'name' => 'Take Action!',
            'url' => 'https://www.google.com',
          ],
        ],
      ],
    ]);
  }

}

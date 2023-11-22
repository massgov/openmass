<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Location Details metadata tests.
 */
class LocationDetailsMetadataTest extends MetadataTestCase {

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
    $node = $this->createNode([
      'type' => 'location_details',
      'title' => 'Test Location Details',
      'field_lede' => 'Test Lede',
      'field_location_details_lede' => [
        'value' => 'Test LD Lede',
        'format' => 'basic_html',
      ],
      'field_state_organization_tax' => [$org_term],
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
      // @todo og:description does not seem to be working here.
      // 'og:description' => '',
      'twitter:description' => 'Test LD Lede',
      'mg_organization' => 'testorgpage',
    ]);
  }

}

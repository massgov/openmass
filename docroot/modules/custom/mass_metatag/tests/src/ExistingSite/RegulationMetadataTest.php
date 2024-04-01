<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Regulation metadata tests.
 */
class RegulationMetadataTest extends MetadataTestCase {

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
      'type' => 'regulation',
      'field_date_published' => '2012-12-31',
      'field_regulation_short_descr' => 'Test Short Description',
      'field_regulation_link_org' => [
        'uri' => 'entity:node/' . $org_node->id(),
      ],
      'field_state_organization_tax' => [$org_term],
      'field_regulation_agency_cmr' => '123',
      'field_regulation_cmr_chapter' => '345',
      'field_regulation_title' => 'Test Regulation',
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
      'og:type' => 'article',
      'twitter:description' => 'Test Short Description',
      'mg_date' => '20121231',
      'mg_type' => 'regulation',
      'mg_organization' => 'testorgpage',
      'twitter:card' => 'summary',
    ]);
  }

}

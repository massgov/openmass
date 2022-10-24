<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Curated list metadata tests.
 */
class CuratedListMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $node = $this->createNode([
      'type' => 'curated_list',
      'title' => 'Test Curated List',
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
      'mg_organization' => 'testorgpage',
    ]);
  }

}

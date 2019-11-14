<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Decision Tree metadata tests.
 */
class DecisionTreeMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => 'published',
    ]);
    $node = $this->createNode([
      'type' => 'decision_tree',
      'title' => 'Test Decision Tree',
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
      'mg_title' => 'Test Decision Tree',
      'og:type' => 'website',
    ]);
  }

}

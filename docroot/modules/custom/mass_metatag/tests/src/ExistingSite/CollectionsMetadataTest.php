<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;

/**
 * Collections metadata tests.
 */
class CollectionsMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $label_term = $this->createTerm(Vocabulary::load('label'), [
      'name' => 'TestTermLabel',
    ]);
    $collection_term = $this->createTerm(Vocabulary::load('collections'), [
      'name' => 'TestTermCollection',
      'field_reusable_label' => [$label_term],
      'field_url_name' => 'test-collection-label',
    ]);

    return $collection_term;
  }

  /**
   * Asserts that a collections page produces the metadata we expect.
   */
  public function testHasExpectedMetadata() {
    $entity = $this->getContent();
    $this->drupalGet('collections/' . $entity->field_url_name->value);
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Collection page was loadable');
    $page = $this->getSession()->getPage();

    $actualMeta = $this->getActualMetatags($page);
    $expectedMeta = $this->getExpectedMetatags($entity);
    foreach ($expectedMeta as $name => $expected) {
      $this->assertArrayHasKey($name, $actualMeta, sprintf('Page has %s metatag', $name));
      $this->assertEquals($expected, $actualMeta[$name], sprintf('%s meta tag matches expected.', $name));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    $labels = \Drupal::service('token')->replace("[term:mass_term_labels]", ['term' => $entity]);
    return [
      'mg_labels' => $labels,
    ];
  }

}

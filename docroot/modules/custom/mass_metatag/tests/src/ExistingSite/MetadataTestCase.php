<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Behat\Mink\Element\DocumentElement;
use Drupal\Core\Entity\ContentEntityInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base test class for metadata checking.
 */
abstract class MetadataTestCase extends ExistingSiteBase {

  private $debug = FALSE;

  /**
   * Return all the content entities for testing.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity that should be tested.
   */
  abstract protected function getContent(): ContentEntityInterface;

  /**
   * Asserts that a single entity produces the metadata we expect.
   */
  public function testHasExpectedMetadata() {
    $entity = $this->getContent();
    $this->indexNode($entity);
    $this->visit($entity->toUrl()->toString(FALSE));
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();

    $actualMeta = $this->getActualMetatags($page);
    $expectedMeta = $this->getExpectedMetatags($entity);
    foreach ($expectedMeta as $name => $expected) {
      $this->assertArrayHasKey($name, $actualMeta, sprintf('Page has %s metatag', $name));
      $this->assertEquals($expected, $actualMeta[$name], sprintf('%s meta tag matches expected.', $name));
    }
    foreach ($this->getExpectedLinks($entity) as $name => $expected) {
      $this->assertEquals($expected, $this->getLink($page, $name), sprintf('%s link tag matches expected.', $name));
    }

    $actualMetadata = $this->parseSchemas($page);
    $expectedMetadata = $this->getExpectedMetadata($entity);
    foreach ($expectedMetadata as $id => $expected) {
      if (!isset($actualMetadata[$id])) {
        $this->fail(sprintf('Metadata not found for %s', $id));
      }
      $this->assertEquals($expected, $actualMetadata[$id], sprintf('Metadata blocks for %s match expectations', $id));
    }
    // Run debug metrics for metatags.  See the function.
    if ($this->debug) {
      $this->debugMetatags($entity, $expectedMeta, $actualMeta);
    }
  }

  /**
   * Run any indexing functions required on the node before using it .
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  protected function indexNode(ContentEntityInterface $entity) {
    /** @var \Drupal\mass_content_api\DescendantManagerInterface $dm */
    $dm = \Drupal::service('descendant_manager');
    $dm->index($entity);
  }

  /**
   * Return the meta tags this entity is expected to have.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity that is being tested.
   *
   * @return array
   *   An associative array, keyed on name, with the expected value.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getExpectedMetatags(ContentEntityInterface $entity) {
    return [
      'title' => sprintf('%s | Mass.gov', $entity->label()),
      'og:title' => $entity->label(),
      'og:site_name' => 'Mass.gov',
      'og:url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'og:type' => 'website',
      'twitter:card' => 'summary',
      'twitter:site' => '@massgov',
      'twitter:site:id' => '16264003',
      'twitter:title' => $entity->label(),
      'twitter:url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }

  /**
   * Return the <link> tags this entity is expected to have.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to return values for.
   *
   * @return array
   *   An associative array, keyed on name, with the expected value.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getExpectedLinks(ContentEntityInterface $entity) {
    return [
      'canonical' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(FALSE),
    ];
  }

  /**
   * Return the expected JSON metadata objects.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to return values for.
   *
   * @return array
   *   The metadata.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return [
      $url . '#header' => [
        '@context' => 'http://schema.org',
        '@type' => 'WPHeader',
        '@id' => $url . '#header',
      ],
      $url . '#footer' => [
        '@context' => 'http://schema.org',
        '@type' => 'WPFooter',
        '@id' => $url . '#footer',
      ],
    ];
  }

  /**
   * Discover all metatags on the page.
   */
  private function getActualMetatags(DocumentElement $page) {
    $tags = [];
    /** @var \Behat\Mink\Element\NodeElement $tag */
    foreach ($page->findAll('css', 'meta[name]') as $tag) {
      $tags[$tag->getAttribute('name')] = $tag->getAttribute('content');
    }
    /** @var \Behat\Mink\Element\NodeElement $tag */
    foreach ($page->findAll('css', 'meta[property]') as $tag) {
      $tags[$tag->getAttribute('property')] = $tag->getAttribute('content');
    }
    // Strip out some metadata we don't care about right now.
    return array_diff_key($tags, [
      'Generator' => '',
      'MobileOptimized' => '',
      'HandheldFriendly' => '',
      'viewport' => '',
    ]);
  }

  /**
   * Retrieve the href value of a single link from the page.
   */
  private function getLink(DocumentElement $page, string $rel) {
    if ($element = $page->find('css', 'link[rel="' . $rel . '"]')) {
      return $element->getAttribute('href');
    }
    throw new \Exception(sprintf('No link %s was found.', $rel));
  }

  /**
   * Parse schema.org JSON-LD metadata.
   */
  private function parseSchemas(DocumentElement $page) {
    $return = [];
    /** @var \Behat\Mink\Element\NodeElement $script */
    foreach ($page->findAll('css', 'script[type="application/ld+json"]') as $script) {
      $obj = $this->flattenSchema(json_decode($script->getText(), TRUE));
      if (isset($obj['@id'])) {
        $return[$obj['@id']] = $obj;
      }
    }
    return $return;
  }

  /**
   * Flatten @graph annotations on a schema.
   */
  private function flattenSchema(array $object) {
    if (isset($object['@graph']) && isset($object['@graph'][0])) {
      $clone = $object + $object['@graph'][0];
      unset($clone['@graph']);
      $clone += $object['@graph'][0];
      return $this->flattenSchema($clone);
    }
    return $object;
  }

  // @codingStandardsIgnoreStart
  /**
   * Capture debugging data about the tags that are found and tested.
   *
   * This captures to a temporary database table, which is queryable using:
   *
   * SELECT tag, COUNT(*) AS 'x Used', SUM(tested) AS 'x Tested',
   * GROUP_CONCAT(bundle ORDER BY bundle SEPARATOR ', ') AS 'Types Using',
   * GROUP_CONCAT(CASE WHEN tested = 1 THEN bundle ELSE NULL END ORDER BY bundle SEPARATOR ', ') AS 'Tested Types',
   * GROUP_CONCAT(CASE WHEN tested = 0 THEN bundle ELSE NULL END ORDER BY bundle SEPARATOR ', ') AS 'Untested Types'
   * FROM `_metatag_debug` GROUP BY tag ORDER BY tag;
   */
  private function debugMetatags(ContentEntityInterface $entity, array $expected, array $actual) {
    $this->ensureMetatagTable('_metatag_debug');
    $tags = array_unique(array_merge(array_keys($actual), array_keys($expected)));
    $db = \Drupal::database();
    $bundle = $entity->bundle();
    $db->delete('_metatag_debug')
      ->condition('bundle', $bundle)
      ->execute();
    foreach ($tags as $tag) {
      $db->insert('_metatag_debug')
        ->fields([
          'bundle' => $bundle,
          'tag' => $tag,
          'used' => (int) isset($actual[$tag]),
          'tested' => (int) isset($expected[$tag]),
        ])
        ->execute();
    }
  }

  private function ensureMetatagTable($table_name) {
    $schema = \Drupal::database()->schema();
    if(!$schema->tableExists($table_name)) {
      $schema->createTable($table_name, [
        'fields' => [
          'bundle' => [
            'type' => 'varchar_ascii',
            'length' => 255,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
          ],
          'tag' => [
            'type' => 'varchar_ascii',
            'length' => 255,
          ],
          'used' => [
            'type' => 'int',
            'size' => 'small',
            'not null' => TRUE,
            'default' => 0,
          ],
          'tested' => [
            'type' => 'int',
            'size' => 'small',
            'not null' => TRUE,
            'default' => 0,
          ],
        ],
        'primary key' => ['bundle', 'tag'],
      ]);
    }
  }
  // @codingStandardsIgnoreEnd

}

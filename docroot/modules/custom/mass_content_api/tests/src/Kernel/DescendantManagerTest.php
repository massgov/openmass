<?php

namespace Drupal\Tests\mass_content_api\Kernel;

use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\mass_content_api\DescendantExtractor;
use Drupal\mass_content_api\DescendantExtractorInterface;
use Drupal\mass_content_api\DescendantManager;
use Drupal\mass_content_api\DescendantStorage;
use Drupal\mass_content_api\DescendantStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the descendant manager.
 */
class DescendantManagerTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * Assert that we indexing removes existing relationships.
   */
  public function testIndexRemovesRelationshipsRegardlessOfExtraction() {
    $node = $this->prophesize(Node::class);
    $node->id()->willReturn(1);
    $node->getEntityTypeId()->willReturn('node');

    $storage = $this->prophesize(DescendantStorageInterface::class);
    $extractor = $this->prophesize(DescendantExtractorInterface::class);
    $extractor->extract($node)->willReturn([]);

    $storage->removeRelationships('node', 1)
      ->shouldBeCalled();
    $storage->removeDebug(Argument::cetera(Argument::any()))->will(function () {});
    $storage->addDebug(Argument::cetera(Argument::any()))->will(function () {});

    $dm = new DescendantManager($storage->reveal(), $extractor->reveal());
    $dm->index($node->reveal());
  }

  /**
   * Assert that indexing causes data to be written to storage.
   */
  public function testIndex() {
    $node = $this->prophesize(Node::class);
    $node->id()->willReturn(1);
    $node->getEntityTypeId()->willReturn('node');

    $storage = $this->prophesize(DescendantStorageInterface::class);
    $extractor = $this->prophesize(DescendantExtractorInterface::class);
    $extractor->extract($node)->willReturn([
      'parents' => [
        'field_something' => [
          ['entity' => 'node', 'id' => 2],
        ]
      ],
      'children' => [
        'field_something_else' => [
          ['entity' => 'node', 'id' => 3],
        ]
      ],
      'linking_pages' => [
        'field_link' => [
          ['entity' => 'node', 'id' => 4]
        ]
      ]
    ]);

    $storage->removeRelationships('node', 1)
      ->shouldBeCalled();
    $storage->addParentChildRelation('node', 1, 'node', 2, 'node', 1)
      ->shouldBeCalled();
    $storage->addParentChildRelation('node', 1, 'node', 1, 'node', 3)
      ->shouldBeCalled();
    $storage->addLinkingPage('node', 1, 'node', 1, 'node', 4)
      ->shouldBeCalled();
    $storage->removeDebug(Argument::cetera(Argument::any()))->will(function () {});
    $storage->addDebug(Argument::cetera(Argument::any()))->will(function () {});

    $dm = new DescendantManager($storage->reveal(), $extractor->reveal());
    $dm->index($node->reveal());
  }

  /**
   * Assert getChildren*() is working as expected.
   */
  public function testGetChildren() {
    $child_1 = ['id' => 2, 'type' => 'location', 'parent' => 1];
    $child_2 = ['id' => 3, 'type' => 'service_page', 'parent' => 2];

    $storage = $this->prophesize(DescendantStorageInterface::class);
    // Note: The output array is keyed by child item's id.
    $storage->getChildren([1])->willReturn(['2' => $child_1]);
    $storage->getChildren([2])->willReturn(['3' => $child_2]);
    $storage->getChildren([3])->willReturn([]);
    $extractor = $this->prophesize(DescendantExtractor::class);
    $dm = new DescendantManager($storage->reveal(), $extractor->reveal());

    // Test getChildrenFlat().
    $this->assertEquals([2, 3], $dm->getChildrenFlat(1));
    $this->assertEquals([2], $dm->getChildrenFlat(1, 1));

    // Test getChildrenLeveled().
    // Note: The output array is keyed by child item's id.
    $this->assertEquals([
      1 => ['2' => $child_1],
      2 => ['3' => $child_2]
    ], $dm->getChildrenLeveled(1));
    $this->assertEquals([
      1 => ['2' => $child_1]
    ], $dm->getChildrenLeveled(1, 1));

    // Test getChildrenTree().
    $this->assertEquals([
      $child_1 + [
        'children' => [
          $child_2 + ['children' => []]
        ]
      ]
    ], $dm->getChildrenTree(1));
    $this->assertEquals([
      $child_1 + [
        'children' => []
      ]
    ], $dm->getChildrenTree(1, 1));
  }

  /**
   * Assert that getParents() is working as expected.
   */
  public function testGetParents() {
    $parent_2 = ['id' => 2, 'type' => 'location', 'child' => 1];
    $parent_3 = ['id' => 3, 'type' => 'service_page', 'child' => 2];

    $storage = $this->prophesize(DescendantStorageInterface::class);
    $storage->getParents([1])->willReturn([$parent_2]);
    $storage->getParents([2])->willReturn([$parent_3]);
    $storage->getParents([3])->willReturn([]);

    $extractor = $this->prophesize(DescendantExtractor::class);
    $dm = new DescendantManager($storage->reveal(), $extractor->reveal());

    $this->assertEquals([
      1 => [$parent_2],
      2 => [$parent_3]
    ], $dm->getParents(1));

    $this->assertEquals([
      1 => [$parent_2],
    ], $dm->getParents(1, 1));
  }

}

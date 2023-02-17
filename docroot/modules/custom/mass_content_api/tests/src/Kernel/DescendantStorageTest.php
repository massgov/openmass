<?php

namespace Drupal\Tests\mass_content_api\Kernel;

use Drupal\Core\Database\Database;
use Drupal\mass_content_api\DescendantStorage;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Descendant Storage.
 */
class DescendantStorageTest extends UnitTestCase {

  /**
   * The SQLite database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * The storage.
   *
   * @var \Drupal\mass_content_api\DescendantStorageInterface
   */
  private $storage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('test', 'test', [
      'driver' => 'sqlite',
      'database' => ':memory:',
    ]);
    $connection = Database::getConnection('test', 'test');
    $connection->schema()->createTable('node_field_data', [
      'fields' => [
        'nid' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'type' => [
          'type' => 'varchar',
          'not null' => TRUE,
        ],
        'status' => [
          'type' => 'int',
          'size' => 'tiny',
          'default' => 1,
        ]
      ]
    ]);
    $connection->insert('node_field_data')
      ->fields(['nid', 'type', 'status'])
      ->values([1, 'type_1', 1])
      ->values([2, 'type_2', 1])
      ->values([3, 'type_3', 1])
      // 4 is reserved as a nonexistent node.
      // If 5 shows up, we are displaying unpublished.
      ->values([5, 'type_5', 0])
      ->execute();
    $this->connection = $connection;
    $this->storage = new DescendantStorage($this->connection);
    $this->storage->addParentChildRelation('node', 1, 'node', 1, 'node', 2);
    $this->storage->addParentChildRelation('node', 1, 'node', 1, 'node', 5);
    $this->storage->addLinkingPage('node', 1, 'node', 1, 'node', 3);
    $this->storage->addLinkingPage('node', 1, 'node', 1, 'node', 5);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();
    // Close the connection so we can reset for the next test.
    unset($this->storage);
    Database::closeConnection('test', 'test');
  }

  /**
   * Assert that getParents returns any empty array when there aren't parents.
   */
  public function testGetParentsEmpty() {
    $this->assertEquals([], $this->storage->getParents([3]));
  }

  /**
   * Assert that getParents returns parents.
   */
  public function testGetParents() {
    $this->assertEquals([
      1 => [
        'id' => '1',
        'child' => '2',
        'type' => 'type_1'
      ]
    ], $this->storage->getParents([2]));
  }

  /**
   * Assert that getChildren returns any empty array when there aren't children.
   */
  public function testGetChildrenEmpty() {
    $this->assertEquals([], $this->storage->getChildren([3]));
  }

  /**
   * Assert that getParents returns parents.
   */
  public function testGetChildren() {
    $this->assertEquals([
      2 => [
        'id' => '2',
        'parent' => '1',
        'type' => 'type_2'
      ]
    ], $this->storage->getChildren([1]));
  }

  /**
   * Assert that getLinksTo returns links.
   */
  public function testGetLinksTo() {
    $this->assertEquals([1], $this->storage->getLinksTo('node', 3));
  }

  /**
   * Assert that getLinksTo returns an empty array when there aren't links.
   */
  public function testGetLinksEmpty() {
    $this->assertEquals([], $this->storage->getLinksTo('node', 2));
  }

  /**
   * Assert that removeRelationships removes parents and links.
   */
  public function testRemoveRemovesReported() {
    $this->storage->removeRelationships('node', 1);
    $this->assertFalse((bool) $this->storage->getChildren([1]));
    $this->assertFalse((bool) $this->storage->getLinksTo('node', 3));
  }

  /**
   * Assert that removeRelatinships does not affect non-reporting entities.
   */
  public function testRemoveOnlyRemovesForReporter() {
    $this->storage->removeRelationships('node', 2);
    $this->assertTrue((bool) $this->storage->getChildren([1]));
  }

}

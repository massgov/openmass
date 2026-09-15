<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit\Hook;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mass_caching\Hook\EntityPurgeHooks;
use Drupal\mass_caching\ManualPurger;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity purge hook implementations.
 */
#[CoversClass(EntityPurgeHooks::class)]
#[Group('mass_caching')]
class EntityPurgeHooksTest extends UnitTestCase {

  /**
   * Tests that a published node purges internal, current alias, and old alias.
   */
  public function testPublishedNodePurgesPublicPaths(): void {
    $original = $this->createNode(id: '123', published: TRUE, path_alias: '/old-alias');
    $node = $this->createNode(
      id: '123',
      published: TRUE,
      path_alias: '/new-alias',
      original: $original,
    );

    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->once())
      ->method('getAliasByPath')
      ->with('/node/123')
      ->willReturn('/current-alias');

    $purged_paths = [];
    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->exactly(4))
      ->method('purgePath')
      ->willReturnCallback(static function (string $path) use (&$purged_paths): void {
        $purged_paths[] = $path;
      });

    (new EntityPurgeHooks($manual_purger, $alias_manager))->purgeEntityInsertOrUpdate($node);

    $this->assertEqualsCanonicalizing(
      ['/node/123', '/current-alias', '/new-alias', '/old-alias'],
      $purged_paths,
    );
  }

  /**
   * Tests that a draft-only node does not purge public paths.
   */
  public function testDraftOnlyNodeDoesNotPurge(): void {
    $node = $this->createNode(id: '123', published: FALSE, path_alias: '/draft-alias');

    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->never())
      ->method('getAliasByPath');

    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->never())
      ->method('purgePath');

    (new EntityPurgeHooks($manual_purger, $alias_manager))->purgeEntityInsertOrUpdate($node);
  }

  /**
   * Tests that unpublishing a node purges the public paths.
   */
  public function testUnpublishedNodeWithPublishedOriginalPurges(): void {
    $original = $this->createNode(id: '123', published: TRUE, path_alias: '/published-alias');
    $node = $this->createNode(
      id: '123',
      published: FALSE,
      path_alias: '/published-alias',
      original: $original,
    );

    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->once())
      ->method('getAliasByPath')
      ->with('/node/123')
      ->willReturn('/node/123');

    $purged_paths = [];
    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->exactly(2))
      ->method('purgePath')
      ->willReturnCallback(static function (string $path) use (&$purged_paths): void {
        $purged_paths[] = $path;
      });

    (new EntityPurgeHooks($manual_purger, $alias_manager))->purgeEntityInsertOrUpdate($node);

    $this->assertEqualsCanonicalizing(['/node/123', '/published-alias'], $purged_paths);
  }

  /**
   * Tests that non-default revisions do not purge public paths.
   */
  public function testNonDefaultRevisionDoesNotPurge(): void {
    $node = $this->createNode(
      id: '123',
      published: TRUE,
      path_alias: '/published-alias',
      default_revision: FALSE,
    );

    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->never())
      ->method('getAliasByPath');

    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->never())
      ->method('purgePath');

    (new EntityPurgeHooks($manual_purger, $alias_manager))->purgeEntityInsertOrUpdate($node);
  }

  /**
   * Creates a node mock for entity hook tests.
   */
  private function createNode(
    string $id,
    bool $published,
    ?string $path_alias,
    ?NodeInterface $original = NULL,
    bool $default_revision = TRUE,
  ): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($id);
    $node->method('isPublished')->willReturn($published);
    $node->method('isDefaultRevision')->willReturn($default_revision);
    $node->method('getOriginal')->willReturn($original);

    $node->method('hasField')
      ->with('path')
      ->willReturn($path_alias !== NULL);

    if ($path_alias !== NULL) {
      $item = $this->createMock(FieldItemInterface::class);
      $item->method('getValue')->willReturn(['alias' => $path_alias]);

      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('isEmpty')->willReturn(FALSE);
      $field->method('first')->willReturn($item);

      $node->method('get')
        ->with('path')
        ->willReturn($field);
    }

    return $node;
  }

}

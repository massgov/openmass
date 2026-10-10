<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit\Hook;

use Drupal\mass_caching\Hook\PathAliasPurgeHooks;
use Drupal\mass_caching\ManualPurger;
use Drupal\path_alias\PathAliasInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests path alias purge hook implementations.
 */
#[CoversClass(PathAliasPurgeHooks::class)]
#[Group('mass_caching')]
class PathAliasPurgeHooksTest extends UnitTestCase {

  /**
   * Tests that newly-created aliases are purged.
   */
  public function testPathAliasInsertPurgesAlias(): void {
    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->once())
      ->method('purgePath')
      ->with('/new-alias');

    $path = $this->createMock(PathAliasInterface::class);
    $path->method('getAlias')->willReturn('/new-alias');

    (new PathAliasPurgeHooks($manual_purger))->pathAliasInsert($path);
  }

  /**
   * Tests that only changed aliases are purged on update.
   */
  public function testPathAliasUpdatePurgesChangedAlias(): void {
    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->once())
      ->method('purgePath')
      ->with('/new-alias');

    $original = $this->createMock(PathAliasInterface::class);
    $original->method('getAlias')->willReturn('/old-alias');

    $path = $this->createMock(PathAliasInterface::class);
    $path->method('getAlias')->willReturn('/new-alias');
    $path->method('getOriginal')->willReturn($original);

    (new PathAliasPurgeHooks($manual_purger))->pathAliasUpdate($path);
  }

  /**
   * Tests that unchanged aliases are not purged on update.
   */
  public function testPathAliasUpdateSkipsUnchangedAlias(): void {
    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->never())
      ->method('purgePath');

    $original = $this->createMock(PathAliasInterface::class);
    $original->method('getAlias')->willReturn('/same-alias');

    $path = $this->createMock(PathAliasInterface::class);
    $path->method('getAlias')->willReturn('/same-alias');
    $path->method('getOriginal')->willReturn($original);

    (new PathAliasPurgeHooks($manual_purger))->pathAliasUpdate($path);
  }

}
